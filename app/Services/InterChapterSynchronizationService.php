<?php

namespace App\Services;

use App\Models\BloodInventory;
use App\Models\Chapter;
use App\Models\InventorySyncLog;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Collection;

class InterChapterSynchronizationService
{
    public function broadcastInventoryUpdate(BloodInventory $inventory): void
    {
        $chapter = $inventory->chapter;

        if (! $chapter) {
            return;
        }

        $payload = [
            'chapter_id' => $chapter->id,
            'chapter_name' => $chapter->chapter_name,
            'blood_type' => $inventory->blood_type,
            'component_type' => $inventory->component_type,
            'units_available' => $inventory->units_available,
            'units_reserved' => $inventory->units_reserved,
            'units_in_transit' => $inventory->units_in_transit,
            'expiration_date' => optional($inventory->expiration_date)->toDateString(),
            'inventory_status' => $inventory->inventory_status,
            'last_updated_at' => optional($inventory->last_updated_at)->toDateTimeString(),
        ];

        event(new \App\Events\ChapterInventoryUpdated($payload));

        try {
            Redis::publish('chapter-inventory-updates', json_encode([
                'type' => 'chapter.inventory.updated',
                'data' => $payload,
            ]));
        } catch (\Throwable $exception) {
            // Redis may be unavailable in some environments; preserve update delivery through Laravel event dispatch.
        }

        $this->logSyncEvent($chapter, 'update', $payload);
    }

    public function getSharedInventorySnapshot(?Chapter $chapter = null): Collection
    {
        $query = BloodInventory::query()
            ->where('inventory_status', 'available')
            ->where('units_available', '>', 0);

        if ($chapter !== null) {
            $query->where('chapter_id', '!=', $chapter->id);
        }

        return $query->with('chapter')
            ->orderBy('blood_type')
            ->orderBy('component_type')
            ->get();
    }

    public function getNearbyChapterAvailability(Chapter $chapter, int $distanceKm = 100): Collection
    {
        $allNearby = Chapter::getNearbyChapters(
            $chapter->latitude,
            $chapter->longitude,
            $distanceKm
        );

        $nearbyIds = $allNearby->pluck('id')->all();

        return BloodInventory::query()
            ->whereIn('chapter_id', $nearbyIds)
            ->where('inventory_status', 'available')
            ->where('units_available', '>', 0)
            ->with('chapter')
            ->orderBy('blood_type')
            ->orderBy('units_available', 'desc')
            ->get();
    }

    protected function logSyncEvent(Chapter $chapter, string $action, array $payload): InventorySyncLog
    {
        return InventorySyncLog::create([
            'chapter_id' => $chapter->id,
            'sync_action' => $action,
            'blood_type' => $payload['blood_type'] ?? null,
            'component_type' => $payload['component_type'] ?? null,
            'units_changed' => $payload['units_available'] ?? 0,
            'sync_status' => 'completed',
            'previous_state' => null,
            'new_state' => $payload,
            'affected_chapters_count' => 1,
            'notes' => 'Broadcast inventory update to shared view',
            'synced_at' => now(),
        ]);
    }
}
