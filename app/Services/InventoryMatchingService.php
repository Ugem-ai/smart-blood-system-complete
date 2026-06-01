<?php

namespace App\Services;

use App\Models\BloodInventory;
use App\Models\BloodRequest;
use App\Models\Chapter;
use App\Models\InventoryTransfer;
use Illuminate\Support\Collection;

class InventoryMatchingService
{
    public function __construct(
        private readonly InventoryManagementService $inventoryService,
        private readonly InterChapterSynchronizationService $syncService
    ) {
    }

    public function matchRequest(BloodRequest $request, ?Chapter $chapter = null): array
    {
        $chapter = $chapter ?? null;

        if (! $chapter instanceof Chapter) {
            return ['status' => 'error', 'message' => 'Request chapter context is required'];
        }

        $requiredUnits = $request->units_required;
        $bloodType = strtoupper(trim($request->blood_type));
        $componentType = ucwords(strtolower(trim($request->component)));

        $localInventory = $this->searchLocalInventory($chapter, $bloodType, $componentType, $requiredUnits);
        if ($localInventory->sum('units_available') >= $requiredUnits) {
            return ['status' => 'matched', 'source' => 'local', 'inventory' => $localInventory];
        }

        $nearbyInventory = $this->searchNearbyChapterInventory($chapter, $bloodType, $componentType, $requiredUnits);
        if ($nearbyInventory->sum('units_available') >= $requiredUnits) {
            return ['status' => 'matched', 'source' => 'nearby', 'inventory' => $nearbyInventory];
        }

        $regionalInventory = $this->searchRegionalInventory($chapter, $bloodType, $componentType);
        if ($regionalInventory->sum('units_available') >= $requiredUnits) {
            return ['status' => 'matched', 'source' => 'regional', 'inventory' => $regionalInventory];
        }

        $inventory = $localInventory->concat($nearbyInventory)->concat($regionalInventory);

        return ['status' => 'pending_donor_match', 'source' => 'inventory_exhausted', 'inventory' => $inventory];
    }

    public function searchLocalInventory(Chapter $chapter, string $bloodType, string $componentType, int $requiredUnits): Collection
    {
        return $chapter->bloodInventory()
            ->where('blood_type', $bloodType)
            ->where('component_type', $componentType)
            ->where('inventory_status', 'available')
            ->where('units_available', '>', 0)
            ->orderBy('expiration_date')
            ->get();
    }

    public function searchNearbyChapterInventory(Chapter $chapter, string $bloodType, string $componentType, int $requiredUnits, int $radiusKm = 100): Collection
    {
        $nearbyChapters = Chapter::getNearbyChapters($chapter->latitude, $chapter->longitude, $radiusKm);
        $nearbyIds = $nearbyChapters->pluck('id')->all();

        return $this->searchInventoryInChapters($nearbyIds, $bloodType, $componentType);
    }

    public function searchRegionalInventory(Chapter $chapter, string $bloodType, string $componentType): Collection
    {
        $regionalChapters = Chapter::query()
            ->where('region', $chapter->region)
            ->where('id', '!=', $chapter->id)
            ->where('status', 'active')
            ->get();

        return $this->searchInventoryInChapters($regionalChapters->pluck('id')->all(), $bloodType, $componentType);
    }

    protected function searchInventoryInChapters(array $chapterIds, string $bloodType, string $componentType): Collection
    {
        return BloodInventory::query()
            ->whereIn('chapter_id', $chapterIds)
            ->where('blood_type', $bloodType)
            ->where('component_type', $componentType)
            ->where('inventory_status', 'available')
            ->where('units_available', '>', 0)
            ->with('chapter')
            ->orderBy('units_available', 'desc')
            ->orderBy('expiration_date')
            ->get();
    }

    public function recommendTransfer(BloodRequest $request, int $thresholdUnits = 1): Collection
    {
        $chapter = $request->chapter;
        if (!$chapter) {
            return collect();
        }

        $bloodType = strtoupper(trim($request->blood_type));
        $componentType = ucwords(strtolower(trim($request->component)));
        $requiredUnits = $request->units_required;

        $nearbyInventory = $this->searchNearbyChapterInventory($chapter, $bloodType, $componentType, $requiredUnits);

        return $nearbyInventory->filter(function (BloodInventory $inventory) use ($thresholdUnits) {
            return $inventory->units_available >= $thresholdUnits;
        });
    }

    public function escalateRareBlood(BloodRequest $request): Collection
    {
        $bloodType = strtoupper(trim($request->blood_type));
        $componentType = ucwords(strtolower(trim($request->component)));

        return BloodInventory::query()
            ->where('blood_type', $bloodType)
            ->where('component_type', $componentType)
            ->where('inventory_status', 'available')
            ->where('units_available', '>', 0)
            ->with('chapter')
            ->orderBy('chapter.region')
            ->orderBy('units_available', 'desc')
            ->get();
    }

    public function createTransferRecommendation(
        BloodRequest $request,
        Chapter $sourceChapter,
        int $units,
        string $reason,
        string $priority = 'urgent'
    ): InventoryTransfer {
        return InventoryTransfer::create([
            'source_chapter_id' => $sourceChapter->id,
            'destination_chapter_id' => $request->chapter_id,
            'blood_request_id' => $request->id,
            'blood_type' => strtoupper(trim($request->blood_type)),
            'component_type' => ucwords(strtolower(trim($request->component))),
            'units_requested' => $units,
            'priority_level' => $priority,
            'reason_for_transfer' => $reason,
            'created_by_user_id' => auth()->id() ?? 1,
            'transfer_status' => 'pending',
        ]);
    }
}
