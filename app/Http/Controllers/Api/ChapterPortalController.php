<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChapterInventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChapterPortalController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $chapter = $request->attributes->get('chapter');

        return response()->json([
            'data' => [
                'id' => $chapter->id,
                'name' => $chapter->name ?: $chapter->chapter_name,
                'location' => $chapter->location ?: $chapter->city,
                'region' => $chapter->region,
                'is_active' => (bool) ($chapter->is_active ?? ($chapter->status === 'active')),
            ],
        ]);
    }

    public function inventory(Request $request): JsonResponse
    {
        $chapter = $request->attributes->get('chapter');

        $inventory = $chapter->chapterInventories()
            ->orderBy('blood_type')
            ->orderBy('component_type')
            ->get()
            ->map(fn (ChapterInventory $item) => [
                'id' => $item->id,
                'blood_type' => $item->blood_type,
                'component_type' => $item->component_type,
                'units_available' => (int) $item->units_available,
                'status' => $item->status,
                'last_synced_at' => $item->last_synced_at,
            ]);

        return response()->json([
            'data' => $inventory,
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        $chapter = $request->attributes->get('chapter');

        $inventoryRows = [];

        if ($request->has('inventory')) {
            $validated = $request->validate([
                'inventory' => ['required', 'array', 'min:1'],
                'inventory.*.blood_type' => ['required', 'string', 'max:10'],
                'inventory.*.component_type' => ['required', 'string', 'max:100'],
                'inventory.*.units_available' => ['required', 'integer', 'min:0'],
            ]);

            $inventoryRows = $validated['inventory'];
        } else {
            $validated = $request->validate([
                'blood_type' => ['required', 'string', 'max:10'],
                'component_type' => ['required', 'string', 'max:100'],
                'units_available' => ['required', 'integer', 'min:0'],
            ]);

            $inventoryRows = [$validated];
        }

        foreach ($inventoryRows as $row) {
            $units = (int) $row['units_available'];
            $status = $units <= 0 ? 'critical' : ($units <= 5 ? 'low' : 'adequate');

            $chapter->chapterInventories()->updateOrCreate(
                [
                    'blood_type' => strtoupper(trim($row['blood_type'])),
                    'component_type' => trim($row['component_type']),
                ],
                [
                    'units_available' => $units,
                    'status' => $status,
                    'last_synced_at' => now(),
                ],
            );
        }

        $chapter->forceFill([
            'synced_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Inventory sync successful.',
        ]);
    }
}
