<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InventoryMonitoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HospitalInventoryController extends Controller
{
    public function index(Request $request, InventoryMonitoringService $inventoryService): JsonResponse
    {
        $hospital = $request->user()->hospitalProfile;

        if (! $hospital) {
            return response()->json(['message' => 'Hospital profile not found.'], 404);
        }

        return response()->json([
            'data' => [
                'hospital_id' => $hospital->id,
                'inventory' => $hospital->bloodInventories()->orderBy('blood_type')->get(),
                'low_stock_alerts' => $inventoryService->lowStockAlerts($hospital),
            ],
        ]);
    }

    public function update(Request $request, InventoryMonitoringService $inventoryService): JsonResponse
    {
        $validated = $request->validate([
            'inventories' => ['required', 'array', 'min:1'],
            'inventories.*.blood_type' => ['required', 'in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
            'inventories.*.units_available' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);

        $hospital = $request->user()->hospitalProfile;

        if (! $hospital) {
            return response()->json(['message' => 'Hospital profile not found.'], 404);
        }

        $result = $inventoryService->updateHospitalInventory(
            hospital: $hospital,
            inventories: $validated['inventories'],
            actorUserId: $request->user()?->id,
        );

        return response()->json([
            'message' => 'Hospital blood inventory updated.',
            'data' => [
                'hospital_id' => $hospital->id,
                'inventory' => $result['inventory']->values()->all(),
                'low_stock_alerts' => $result['alerts'],
            ],
        ]);
    }
}
