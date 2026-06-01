<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BloodInventory;
use App\Models\Chapter;
use App\Services\InventoryMatchingService;
use App\Services\InterChapterSynchronizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\PersonalAccessToken;

class ChapterInventoryController extends Controller
{
    public function index(Request $request, InterChapterSynchronizationService $syncService): JsonResponse
    {
        $chapters = Chapter::with(['bloodInventory' => fn ($query) => $query->where('units_available', '>', 0)])->get();

        return response()->json([
            'data' => [
                'chapters' => $chapters->map(fn (Chapter $chapter) => [
                    'id' => $chapter->id,
                    'chapter_code' => $chapter->chapter_code,
                    'chapter_name' => $chapter->chapter_name,
                    'region' => $chapter->region,
                    'status' => $chapter->status,
                    'province' => $chapter->province,
                    'city' => $chapter->city,
                    'contact_number' => $chapter->contact_number,
                    'total_units_available' => $chapter->bloodInventory->sum('units_available'),
                ]),
            ],
        ]);
    }

    public function show(Request $request, Chapter $chapter): JsonResponse
    {
        $inventory = $chapter->bloodInventory()
            ->where('units_available', '>', 0)
            ->orderBy('blood_type')
            ->orderBy('component_type')
            ->get();

        return response()->json([
            'data' => [
                'chapter' => $chapter,
                'inventory' => $inventory,
                'low_stock' => $chapter->bloodInventory()->whereColumn('units_available', '<=', 'reorder_level')->get(),
                'critical_shortages' => $chapter->bloodInventory()->whereColumn('units_available', '<=', 'critical_level')->get(),
            ],
        ]);
    }

    public function search(Request $request, InterChapterSynchronizationService $syncService): JsonResponse
    {
        $validated = $request->validate([
            'blood_type' => ['required', 'string'],
            'component_type' => ['nullable', 'string'],
            'region' => ['nullable', 'string'],
            'chapter_id' => ['nullable', 'integer', 'exists:chapters,id'],
            'only_available' => ['sometimes', 'boolean'],
        ]);

        $query = BloodInventory::query()
            ->where('blood_type', strtoupper(trim($validated['blood_type'])));

        if (! empty($validated['component_type'])) {
            $query->where('component_type', ucwords(strtolower(trim($validated['component_type']))));
        }

        if (! empty($validated['region'])) {
            $query->whereHas('chapter', fn ($q) => $q->where('region', $validated['region']));
        }

        if (! empty($validated['chapter_id'])) {
            $query->where('chapter_id', $validated['chapter_id']);
        }

        if (($validated['only_available'] ?? true) === true) {
            $query->where('inventory_status', 'available')->where('units_available', '>', 0);
        }

        $inventory = $query->with('chapter')->orderBy('units_available', 'desc')->get();

        return response()->json([
            'data' => [
                'query' => $validated,
                'inventory' => $inventory,
            ],
        ]);
    }

    public function nearby(Request $request, Chapter $chapter, InterChapterSynchronizationService $syncService): JsonResponse
    {
        $radiusKm = max(10, min(500, (int) $request->input('radius_km', 100)));
        $inventory = $syncService->getNearbyChapterAvailability($chapter, $radiusKm);

        return response()->json([
            'data' => [
                'chapter' => $chapter,
                'radius_km' => $radiusKm,
                'nearby_inventory' => $inventory,
            ],
        ]);
    }

    public function recommendTransfers(Request $request, Chapter $chapter, InventoryMatchingService $matchingService): JsonResponse
    {
        $requiredUnits = max(1, (int) $request->input('units_required', 1));
        $bloodType = strtoupper(trim($request->input('blood_type', '')));
        $componentType = ucwords(strtolower(trim($request->input('component_type', ''))));

        if ($bloodType === '') {
            return response()->json(['message' => 'blood_type is required.'], 422);
        }

        $inventory = $matchingService->searchNearbyChapterInventory($chapter, $bloodType, $componentType, $requiredUnits);

        return response()->json([
            'data' => [
                'chapter' => $chapter,
                'required_units' => $requiredUnits,
                'recommended_transfers' => $inventory,
            ],
        ]);
    }

    public function stream(Request $request)
    {
        if (! $request->user()) {
            $token = $request->query('token');

            if (! $token) {
                return response()->json(['message' => 'Unauthorized.'], 401);
            }

            $personalAccessToken = PersonalAccessToken::findToken($token);

            if (! $personalAccessToken || ! $personalAccessToken->tokenable) {
                return response()->json(['message' => 'Unauthorized.'], 401);
            }

            Auth::setUser($personalAccessToken->tokenable);
        }

        return response()->stream(function () {
            Redis::subscribe(['chapter-inventory-updates'], function ($message, $channel) {
                if ($channel !== 'chapter-inventory-updates') {
                    return;
                }

                echo "event: inventoryUpdate\n";
                echo "data: {$message}\n\n";
                ob_flush();
                flush();
            });
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
