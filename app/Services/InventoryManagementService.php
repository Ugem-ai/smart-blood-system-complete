<?php

namespace App\Services;

use App\Models\BloodInventory;
use App\Models\Chapter;
use App\Models\InventorySyncLog;
use App\Models\InventoryTransaction;
use App\Models\InventoryTransfer;
use App\Models\BloodRequest;
use Carbon\Carbon;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

class InventoryManagementService
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly InterChapterSynchronizationService $syncService
    ) {
    }

    public function createOrUpdateInventory(array $attributes): BloodInventory
    {
        $inventory = BloodInventory::updateOrCreate(
            [
                'chapter_id' => $attributes['chapter_id'],
                'blood_type' => strtoupper(trim($attributes['blood_type'])),
                'component_type' => ucwords(strtolower(trim($attributes['component_type']))),
                'expiration_date' => $attributes['expiration_date'] ?? null,
            ],
            [
                'units_available' => $attributes['units_available'] ?? 0,
                'units_reserved' => $attributes['units_reserved'] ?? 0,
                'units_in_transit' => $attributes['units_in_transit'] ?? 0,
                'inventory_status' => $attributes['inventory_status'] ?? 'available',
                'reserved_for_request_id' => $attributes['reserved_for_request_id'] ?? null,
                'critical_level' => $attributes['critical_level'] ?? 2,
                'reorder_level' => $attributes['reorder_level'] ?? 5,
                'notes' => $attributes['notes'] ?? null,
                'last_updated_at' => now(),
            ]
        );

        $this->logSync(
            $inventory,
            'update',
            $attributes['triggered_by_request_id'] ?? null,
            $attributes['triggered_by_user_id'] ?? null,
            $attributes['notes'] ?? null
        );

        $this->syncService->broadcastInventoryUpdate($inventory);

        return $inventory;
    }

    public function reserveInventory(BloodInventory $inventory, int $units, BloodRequest $request): bool
    {
        if ($inventory->units_available < $units) {
            return false;
        }

        $previousState = $inventory->toArray();

        $inventory->units_available -= $units;
        $inventory->units_reserved += $units;
        $inventory->reserved_for_request_id = $request->id;
        $inventory->inventory_status = 'reserved';
        $inventory->last_updated_at = now();
        $inventory->save();

        $this->recordTransaction(
            $inventory,
            'reserve',
            -$units,
            $previousState['units_available'],
            $inventory->units_available,
            $request->id,
            auth()->id() ?? 1,
            "Reserve units for request #{$request->id}"
        );

        $this->logSync(
            $inventory,
            'reserve',
            $request->id,
            auth()->id(),
            "Reserved {$units} units for request #{$request->id}"
        );

        $this->syncService->broadcastInventoryUpdate($inventory);

        return true;
    }

    public function releaseInventoryReservation(BloodInventory $inventory, int $units, ?BloodRequest $request = null): bool
    {
        if ($inventory->units_reserved < $units) {
            return false;
        }

        $previousState = $inventory->toArray();

        $inventory->units_reserved -= $units;
        $inventory->units_available += $units;

        if ($inventory->units_reserved === 0) {
            $inventory->reserved_for_request_id = null;
            $inventory->inventory_status = 'available';
        }

        $inventory->last_updated_at = now();
        $inventory->save();

        $this->recordTransaction(
            $inventory,
            'release_reservation',
            $units,
            $previousState['units_available'],
            $inventory->units_available,
            $request?->id,
            auth()->id() ?? 1,
            "Released reservation for request #{$request->id}" ?? 'Released reservation'
        );

        $this->logSync(
            $inventory,
            'release',
            $request?->id,
            auth()->id(),
            "Released {$units} reserved units"
        );

        $this->syncService->broadcastInventoryUpdate($inventory);

        return true;
    }

    public function transferInventory(InventoryTransfer $transfer): bool
    {
        return $this->database->transaction(function () use ($transfer) {
            $sourceInventory = BloodInventory::where('chapter_id', $transfer->source_chapter_id)
                ->where('blood_type', $transfer->blood_type)
                ->where('component_type', $transfer->component_type)
                ->where('expiration_date', $transfer->expiration_date)
                ->first();

            if (!$sourceInventory || $sourceInventory->units_available < $transfer->units_approved) {
                return false;
            }

            $destinationInventory = BloodInventory::firstOrCreate(
                [
                    'chapter_id' => $transfer->destination_chapter_id,
                    'blood_type' => $transfer->blood_type,
                    'component_type' => $transfer->component_type,
                    'expiration_date' => $transfer->expiration_date,
                ],
                [
                    'units_available' => 0,
                    'units_reserved' => 0,
                    'units_in_transit' => 0,
                    'inventory_status' => 'in_transit',
                    'critical_level' => 2,
                    'reorder_level' => 5,
                    'notes' => null,
                    'last_updated_at' => now(),
                ]
            );

            $sourceBefore = $sourceInventory->toArray();
            $destinationBefore = $destinationInventory->toArray();

            $sourceInventory->units_available -= $transfer->units_approved;
            $sourceInventory->units_in_transit += $transfer->units_approved;
            $sourceInventory->last_updated_at = now();
            $sourceInventory->save();

            $destinationInventory->units_in_transit += $transfer->units_approved;
            $destinationInventory->inventory_status = 'in_transit';
            $destinationInventory->last_updated_at = now();
            $destinationInventory->save();

            $transfer->markInTransit();
            $transfer->units_transferred = $transfer->units_approved;
            $transfer->save();

            $this->recordTransaction(
                $sourceInventory,
                'transfer_out',
                -$transfer->units_approved,
                $sourceBefore['units_available'],
                $sourceInventory->units_available,
                $transfer->blood_request_id,
                auth()->id() ?? 1,
                "Transfer out {$transfer->units_approved} units to chapter {$transfer->destination_chapter_id}"
            );

            $this->recordTransaction(
                $destinationInventory,
                'transfer_in',
                $transfer->units_approved,
                $destinationBefore['units_in_transit'],
                $destinationInventory->units_in_transit,
                $transfer->blood_request_id,
                auth()->id() ?? 1,
                "Transfer in {$transfer->units_approved} units from chapter {$transfer->source_chapter_id}"
            );

            $this->syncService->broadcastInventoryUpdate($sourceInventory);
            $this->syncService->broadcastInventoryUpdate($destinationInventory);

            return true;
        });
    }

    public function completeTransfer(InventoryTransfer $transfer): bool
    {
        return $this->database->transaction(function () use ($transfer) {
            $destinationInventory = BloodInventory::where('chapter_id', $transfer->destination_chapter_id)
                ->where('blood_type', $transfer->blood_type)
                ->where('component_type', $transfer->component_type)
                ->where('expiration_date', $transfer->expiration_date)
                ->first();

            if (!$destinationInventory) {
                return false;
            }

            $beforeDestination = $destinationInventory->toArray();

            $destinationInventory->units_in_transit -= $transfer->units_transferred;
            $destinationInventory->units_available += $transfer->units_transferred;
            $destinationInventory->inventory_status = 'available';
            $destinationInventory->last_updated_at = now();
            $destinationInventory->save();

            $transfer->complete($transfer->units_transferred ?? 0);

            $this->recordTransaction(
                $destinationInventory,
                'usage',
                0,
                $beforeDestination['units_available'],
                $destinationInventory->units_available,
                $transfer->blood_request_id,
                auth()->id() ?? 1,
                "Completed transfer of {$transfer->units_transferred} units"
            );

            $this->syncService->broadcastInventoryUpdate($destinationInventory);

            return true;
        });
    }

    public function detectLowStock(Chapter $chapter): Collection
    {
        return $chapter->bloodInventory()
            ->where('inventory_status', 'available')
            ->whereColumn('units_available', '<=', 'reorder_level')
            ->orderBy('units_available')
            ->get();
    }

    public function detectCriticalShortages(Chapter $chapter): Collection
    {
        return $chapter->bloodInventory()
            ->where('inventory_status', 'available')
            ->whereColumn('units_available', '<=', 'critical_level')
            ->orderBy('units_available')
            ->get();
    }

    protected function recordTransaction(
        BloodInventory $inventory,
        string $type,
        int $quantityChanged,
        int $quantityBefore,
        int $quantityAfter,
        ?int $requestId,
        int $performedBy,
        string $reason
    ): InventoryTransaction {
        return InventoryTransaction::create([
            'blood_inventory_id' => $inventory->id,
            'chapter_id' => $inventory->chapter_id,
            'transaction_type' => $type,
            'quantity_changed' => $quantityChanged,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'blood_request_id' => $requestId,
            'performed_by_user_id' => $performedBy,
            'reason' => $reason,
        ]);
    }

    protected function logSync(
        BloodInventory $inventory,
        string $action,
        ?int $requestId,
        ?int $userId,
        ?string $notes = null
    ): InventorySyncLog {
        return InventorySyncLog::create([
            'chapter_id' => $inventory->chapter_id,
            'sync_action' => $action,
            'blood_type' => $inventory->blood_type,
            'component_type' => $inventory->component_type,
            'units_changed' => $inventory->units_available,
            'sync_status' => 'completed',
            'previous_state' => null,
            'new_state' => $inventory->toArray(),
            'triggered_by_request_id' => $requestId,
            'triggered_by_user_id' => $userId,
            'affected_chapters_count' => 1,
            'notes' => $notes,
            'synced_at' => now(),
        ]);
    }
}
