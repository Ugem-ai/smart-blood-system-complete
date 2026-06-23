<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\ChapterApiKey;
use App\Models\ChapterInventory;
use App\Models\ChapterTransferRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminChapterManagementController extends Controller
{
    public function chapters(Request $request): JsonResponse
    {
        $chapters = Chapter::query()
            ->withCount([
                'chapterInventories as inventory_lines_count',
                'chapterInventories as critical_count' => fn ($query) => $query->where('units_available', '<=', 0),
            ])
            ->with(['chapterInventories' => fn ($query) => $query
                ->orderBy('blood_type')
                ->orderBy('component_type')])
            ->orderByRaw('COALESCE(name, chapter_name)')
            ->get();

        $latestSync = $chapters
            ->flatMap(fn (Chapter $chapter) => $chapter->chapterInventories->pluck('last_synced_at')->filter())
            ->max();

        $availableInventoryLines = $chapters->reduce(
            fn (int $total, Chapter $chapter) => $total + (int) ($chapter->inventory_lines_count ?? 0),
            0,
        );

        $criticalAlerts = $chapters->reduce(
            fn (int $total, Chapter $chapter) => $total + (int) ($chapter->critical_count ?? 0),
            0,
        );

        return response()->json([
            'data' => [
                'chapters' => $chapters->map(fn (Chapter $chapter) => $this->transformChapterSummary($chapter)),
                'kpis' => [
                    'total_chapters' => $chapters->count(),
                    'available_inventory_lines' => $availableInventoryLines,
                    'critical_alerts' => $criticalAlerts,
                    'last_sync_at' => $latestSync,
                ],
            ],
        ]);
    }

    public function createChapter(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $chapter = Chapter::query()->create([
            'name' => $validated['name'],
            'location' => $validated['location'],
            'region' => $validated['region'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'chapter_name' => $validated['name'],
            'city' => $validated['location'],
            'status' => ($validated['is_active'] ?? true) ? 'active' : 'inactive',
            'chapter_code' => 'CH-'.Str::upper(Str::random(8)),
        ]);

        return response()->json([
            'message' => 'Chapter created successfully.',
            'data' => $this->transformChapterSummary($chapter->load('chapterInventories')),
        ], 201);
    }

    public function showChapter(Chapter $chapter): JsonResponse
    {
        $chapter->load([
            'chapterInventories' => fn ($query) => $query->orderBy('blood_type')->orderBy('component_type'),
            'apiKeys' => fn ($query) => $query->orderByDesc('created_at'),
        ]);

        return response()->json([
            'data' => [
                'chapter' => $this->transformChapterSummary($chapter),
                'inventory' => $chapter->chapterInventories->map(fn (ChapterInventory $item) => $this->transformInventoryRow($item)),
                'api_keys' => $chapter->apiKeys->map(fn (ChapterApiKey $key) => $this->transformApiKey($key)),
            ],
        ]);
    }

    public function updateChapter(Request $request, Chapter $chapter): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'location' => ['sometimes', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('name', $validated)) {
            $chapter->name = $validated['name'];
            $chapter->chapter_name = $validated['name'];
        }

        if (array_key_exists('location', $validated)) {
            $chapter->location = $validated['location'];
            $chapter->city = $validated['location'];
        }

        if (array_key_exists('region', $validated)) {
            $chapter->region = $validated['region'];
        }

        if (array_key_exists('latitude', $validated)) {
            $chapter->latitude = $validated['latitude'];
        }

        if (array_key_exists('longitude', $validated)) {
            $chapter->longitude = $validated['longitude'];
        }

        if (array_key_exists('is_active', $validated)) {
            $chapter->is_active = $validated['is_active'];
            $chapter->status = $validated['is_active'] ? 'active' : 'inactive';
        }

        $chapter->save();

        return response()->json([
            'message' => 'Chapter updated successfully.',
            'data' => $this->transformChapterSummary($chapter->fresh('chapterInventories')),
        ]);
    }

    public function deleteChapter(Chapter $chapter): JsonResponse
    {
        $chapter->delete();

        return response()->json([
            'message' => 'Chapter deleted successfully.',
        ]);
    }

    public function chapterInventory(Request $request, Chapter $chapter): JsonResponse
    {
        $validated = $request->validate([
            'blood_type' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', 'in:adequate,low,critical'],
        ]);

        $inventoryQuery = $chapter->chapterInventories()->orderBy('blood_type')->orderBy('component_type');

        if (! empty($validated['blood_type'])) {
            $inventoryQuery->where('blood_type', strtoupper(trim($validated['blood_type'])));
        }

        if (! empty($validated['status'])) {
            $inventoryQuery->where('status', $validated['status']);
        }

        $inventory = $inventoryQuery->get();

        return response()->json([
            'data' => [
                'chapter_id' => $chapter->id,
                'inventory' => $inventory->map(fn (ChapterInventory $item) => $this->transformInventoryRow($item)),
            ],
        ]);
    }

    public function updateInventory(Request $request, Chapter $chapter, ChapterInventory $inventory): JsonResponse
    {
        if ((int) $inventory->chapter_id !== (int) $chapter->id) {
            return response()->json(['message' => 'Inventory row does not belong to chapter.'], 422);
        }

        $validated = $request->validate([
            'units_available' => ['required', 'integer', 'min:0'],
            'status' => ['nullable', 'in:adequate,low,critical'],
            'last_synced_at' => ['nullable', 'date'],
        ]);

        $inventory->units_available = $validated['units_available'];
        $inventory->status = $validated['status'] ?? $this->deriveInventoryStatus($validated['units_available']);
        $inventory->last_synced_at = $validated['last_synced_at'] ?? now();
        $inventory->save();

        return response()->json([
            'message' => 'Inventory updated successfully.',
            'data' => $this->transformInventoryRow($inventory->fresh()),
        ]);
    }

    public function listTransfers(): JsonResponse
    {
        $transfers = ChapterTransferRequest::query()
            ->with(['sourceChapter', 'destinationChapter'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $transfers,
        ]);
    }

    public function createTransfer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_chapter_id' => ['required', 'integer', 'exists:chapters,id'],
            'destination_chapter_id' => ['required', 'integer', 'exists:chapters,id'],
            'blood_type' => ['required', 'string', 'max:10'],
            'component_type' => ['required', 'string', 'max:100'],
            'units_requested' => ['required', 'integer', 'min:1'],
            'priority' => ['required', 'in:routine,urgent,emergency'],
            'reason' => ['nullable', 'string'],
        ]);

        if ((int) $validated['source_chapter_id'] === (int) $validated['destination_chapter_id']) {
            return response()->json(['message' => 'Source and destination chapter must be different.'], 422);
        }

        $availableUnits = $this->sourceAvailableUnits(
            sourceChapterId: (int) $validated['source_chapter_id'],
            bloodType: strtoupper(trim($validated['blood_type'])),
            componentType: trim($validated['component_type']),
        );

        if ($availableUnits < (int) $validated['units_requested']) {
            return response()->json([
                'message' => 'Requested units exceed available source inventory.',
                'data' => [
                    'available_units' => $availableUnits,
                ],
            ], 422);
        }

        $transfer = ChapterTransferRequest::query()->create([
            'source_chapter_id' => $validated['source_chapter_id'],
            'destination_chapter_id' => $validated['destination_chapter_id'],
            'blood_type' => strtoupper(trim($validated['blood_type'])),
            'component_type' => trim($validated['component_type']),
            'units_requested' => $validated['units_requested'],
            'priority' => $validated['priority'],
            'reason' => $validated['reason'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Transfer request submitted.',
            'data' => $transfer,
        ], 201);
    }

    public function updateTransferStatus(Request $request, ChapterTransferRequest $transfer): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,approved,rejected,completed'],
            'rejection_reason' => ['nullable', 'string'],
        ]);

        $targetStatus = $validated['status'];
        $currentStatus = $transfer->status;

        if ($targetStatus === 'approved' && $currentStatus !== 'pending') {
            return response()->json(['message' => 'Only pending transfers can be approved.'], 422);
        }

        if ($targetStatus === 'rejected' && ! in_array($currentStatus, ['pending', 'approved'], true)) {
            return response()->json(['message' => 'This transfer cannot be rejected in its current state.'], 422);
        }

        if ($targetStatus === 'completed') {
            if ($currentStatus !== 'approved') {
                return response()->json(['message' => 'Transfer must be approved before completion.'], 422);
            }

            DB::transaction(function () use ($transfer): void {
                $sourceRow = ChapterInventory::query()
                    ->where('chapter_id', $transfer->source_chapter_id)
                    ->where('blood_type', $transfer->blood_type)
                    ->where('component_type', $transfer->component_type)
                    ->lockForUpdate()
                    ->first();

                $sourceUnits = (int) ($sourceRow?->units_available ?? 0);
                if ($sourceUnits < (int) $transfer->units_requested) {
                    throw ValidationException::withMessages([
                        'units_requested' => 'Insufficient source inventory to complete transfer.',
                    ]);
                }

                if (! $sourceRow) {
                    throw ValidationException::withMessages([
                        'source_chapter_id' => 'Source inventory line not found.',
                    ]);
                }

                $sourceRow->units_available = $sourceUnits - (int) $transfer->units_requested;
                $sourceRow->status = $this->deriveInventoryStatus((int) $sourceRow->units_available);
                $sourceRow->last_synced_at = now();
                $sourceRow->save();

                $destinationRow = ChapterInventory::query()->lockForUpdate()->firstOrCreate(
                    [
                        'chapter_id' => $transfer->destination_chapter_id,
                        'blood_type' => $transfer->blood_type,
                        'component_type' => $transfer->component_type,
                    ],
                    [
                        'units_available' => 0,
                        'status' => 'critical',
                        'last_synced_at' => now(),
                    ],
                );

                $destinationRow->units_available = (int) $destinationRow->units_available + (int) $transfer->units_requested;
                $destinationRow->status = $this->deriveInventoryStatus((int) $destinationRow->units_available);
                $destinationRow->last_synced_at = now();
                $destinationRow->save();

                $transfer->status = 'completed';
                $transfer->save();
            });

            return response()->json([
                'message' => 'Transfer status updated.',
                'data' => $transfer->fresh(),
            ]);
        }

        $transfer->status = $targetStatus;
        if ($targetStatus === 'rejected' && ! empty($validated['rejection_reason'])) {
            $transfer->reason = trim(($transfer->reason ? $transfer->reason.' | ' : '').'Rejected: '.$validated['rejection_reason']);
        }
        $transfer->save();

        return response()->json([
            'message' => 'Transfer status updated.',
            'data' => $transfer,
        ]);
    }

    public function recommendations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'blood_type' => ['required', 'string', 'max:10'],
            'units' => ['required', 'integer', 'min:1'],
            'destination_chapter_id' => ['required', 'integer', 'exists:chapters,id'],
        ]);

        $destinationChapter = Chapter::query()->findOrFail($validated['destination_chapter_id']);

        $candidates = ChapterInventory::query()
            ->with('chapter')
            ->where('blood_type', strtoupper(trim($validated['blood_type'])))
            ->where('units_available', '>=', (int) $validated['units'])
            ->whereHas('chapter', fn ($query) => $query->where('id', '!=', $destinationChapter->id))
            ->orderByDesc('units_available')
            ->get()
            ->map(function (ChapterInventory $inventory) use ($destinationChapter) {
                return [
                    'inventory_id' => $inventory->id,
                    'chapter_id' => $inventory->chapter_id,
                    'chapter_name' => $inventory->chapter->name ?: $inventory->chapter->chapter_name,
                    'location' => $inventory->chapter->location ?: $inventory->chapter->city,
                    'distance_km' => $this->calculateDistance($destinationChapter, $inventory->chapter),
                    'units_available' => $inventory->units_available,
                    'blood_type' => $inventory->blood_type,
                    'component_type' => $inventory->component_type,
                ];
            })
            ->sortBy('distance_km')
            ->values();

        return response()->json([
            'data' => [
                'destination_chapter_id' => $destinationChapter->id,
                'recommended_sources' => $candidates,
            ],
        ]);
    }

    public function nearby(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'chapter_id' => ['required', 'integer', 'exists:chapters,id'],
            'radius' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $radius = (int) ($validated['radius'] ?? 100);
        $origin = Chapter::query()->findOrFail($validated['chapter_id']);

        $chapters = Chapter::query()
            ->where('id', '!=', $origin->id)
            ->get()
            ->map(function (Chapter $chapter) use ($origin) {
                return [
                    'chapter' => [
                        'id' => $chapter->id,
                        'name' => $chapter->name ?: $chapter->chapter_name,
                        'location' => $chapter->location ?: $chapter->city,
                    ],
                    'distance_km' => $this->calculateDistance($origin, $chapter),
                    'stock_summary' => $chapter->chapterInventories()
                        ->select('blood_type', DB::raw('SUM(units_available) as units_available'))
                        ->groupBy('blood_type')
                        ->orderBy('blood_type')
                        ->get(),
                ];
            })
            ->filter(fn (array $row) => $row['distance_km'] <= $radius)
            ->values();

        return response()->json([
            'data' => [
                'origin_chapter_id' => $origin->id,
                'radius' => $radius,
                'nearby_chapters' => $chapters,
            ],
        ]);
    }

    public function listApiKeys(Chapter $chapter): JsonResponse
    {
        $keys = $chapter->apiKeys()->where('is_active', true)->orderByDesc('created_at')->get();

        return response()->json([
            'data' => $keys->map(fn (ChapterApiKey $key) => $this->transformApiKey($key)),
        ]);
    }

    public function generateApiKey(Request $request, Chapter $chapter): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        $plainTextKey = 'sb_ch_'.Str::random(48);

        $key = $chapter->apiKeys()->create([
            'api_key' => $plainTextKey,
            'label' => $validated['label'] ?? ('Generated '.now()->format('Y-m-d H:i')),
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'API key generated successfully.',
            'data' => [
                'key' => $this->transformApiKey($key),
                'plain_text_key' => $plainTextKey,
            ],
        ], 201);
    }

    public function revokeApiKey(Chapter $chapter, ChapterApiKey $key): JsonResponse
    {
        if ((int) $key->chapter_id !== (int) $chapter->id) {
            return response()->json(['message' => 'API key does not belong to chapter.'], 422);
        }

        $key->is_active = false;
        $key->save();

        return response()->json([
            'message' => 'API key revoked.',
        ]);
    }

    private function transformChapterSummary(Chapter $chapter): array
    {
        $inventory = $chapter->relationLoaded('chapterInventories') ? $chapter->chapterInventories : collect();

        $criticalCount = $inventory->where('units_available', '<=', 0)->count();
        $lowCount = $inventory->where('units_available', '>', 0)->where('units_available', '<=', 5)->count();
        $bloodTypesCount = $inventory->pluck('blood_type')->filter()->unique()->count();
        $lastSynced = $inventory->max('last_synced_at') ?: $chapter->synced_at;

        return [
            'id' => $chapter->id,
            'name' => $chapter->name ?: $chapter->chapter_name,
            'location' => $chapter->location ?: $chapter->city ?: $chapter->province ?: $chapter->region,
            'region' => $chapter->region,
            'latitude' => $chapter->latitude,
            'longitude' => $chapter->longitude,
            'is_active' => (bool) ($chapter->is_active ?? ($chapter->status === 'active')),
            'sync_status' => $this->syncStatusFromTimestamp($lastSynced),
            'last_synced_at' => $lastSynced,
            'inventory_lines_count' => $inventory->count(),
            'blood_types_count' => $bloodTypesCount,
            'critical_count' => $criticalCount,
            'low_count' => $lowCount,
            'inventory_summary' => sprintf('%d blood types - %d critical', $bloodTypesCount, $criticalCount),
        ];
    }

    private function transformInventoryRow(ChapterInventory $item): array
    {
        $status = $item->status ?: $this->deriveInventoryStatus((int) $item->units_available);

        return [
            'id' => $item->id,
            'blood_type' => $item->blood_type,
            'component_type' => $item->component_type,
            'units_available' => (int) $item->units_available,
            'status' => $status,
            'last_updated' => $item->last_synced_at ?: $item->updated_at,
        ];
    }

    private function transformApiKey(ChapterApiKey $key): array
    {
        return [
            'id' => $key->id,
            'label' => $key->label,
            'key_masked' => Str::mask($key->api_key, '*', 8),
            'created_at' => $key->created_at,
            'last_used_at' => $key->last_used_at,
            'is_active' => (bool) $key->is_active,
        ];
    }

    private function deriveInventoryStatus(int $units): string
    {
        if ($units <= 0) {
            return 'critical';
        }

        if ($units <= 5) {
            return 'low';
        }

        return 'adequate';
    }

    private function syncStatusFromTimestamp($timestamp): string
    {
        if (! $timestamp) {
            return 'offline';
        }

        $hours = now()->diffInHours($timestamp);

        if ($hours <= 24) {
            return 'live';
        }

        if ($hours <= 72) {
            return 'stale';
        }

        return 'offline';
    }

    private function calculateDistance(Chapter $origin, Chapter $target): float
    {
        if (! $origin->latitude || ! $origin->longitude || ! $target->latitude || ! $target->longitude) {
            return 9999;
        }

        $earthRadiusKm = 6371;
        $dLat = deg2rad((float) $target->latitude - (float) $origin->latitude);
        $dLon = deg2rad((float) $target->longitude - (float) $origin->longitude);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad((float) $origin->latitude)) * cos(deg2rad((float) $target->latitude))
            * sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadiusKm * $c, 2);
    }

    private function sourceAvailableUnits(int $sourceChapterId, string $bloodType, string $componentType): int
    {
        return (int) ChapterInventory::query()
            ->where('chapter_id', $sourceChapterId)
            ->where('blood_type', $bloodType)
            ->where('component_type', $componentType)
            ->sum('units_available');
    }
}
