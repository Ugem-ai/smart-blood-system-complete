<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\InventoryTransfer;
use App\Services\InventoryManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryTransferController extends Controller
{
    public function store(Request $request, InventoryManagementService $inventoryService): JsonResponse
    {
        $validated = $request->validate([
            'source_chapter_id' => ['required', 'integer', 'exists:chapters,id'],
            'destination_chapter_id' => ['required', 'integer', 'exists:chapters,id'],
            'blood_type' => ['required', 'string'],
            'component_type' => ['required', 'string'],
            'units_requested' => ['required', 'integer', 'min:1'],
            'expiration_date' => ['nullable', 'date'],
            'priority_level' => ['required', 'in:routine,urgent,emergency'],
            'reason_for_transfer' => ['nullable', 'string'],
        ]);

        if ($validated['source_chapter_id'] === $validated['destination_chapter_id']) {
            return response()->json(['message' => 'Source and destination chapters must differ.'], 422);
        }

        $transfer = InventoryTransfer::create([
            'source_chapter_id' => $validated['source_chapter_id'],
            'destination_chapter_id' => $validated['destination_chapter_id'],
            'blood_request_id' => $request->input('blood_request_id'),
            'blood_type' => strtoupper(trim($validated['blood_type'])),
            'component_type' => ucwords(strtolower(trim($validated['component_type']))),
            'units_requested' => $validated['units_requested'],
            'priority_level' => $validated['priority_level'],
            'reason_for_transfer' => $validated['reason_for_transfer'] ?? null,
            'created_by_user_id' => $request->user()->id,
            'transfer_status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Inventory transfer request created.',
            'data' => $transfer,
        ], 201);
    }

    public function show(InventoryTransfer $inventoryTransfer): JsonResponse
    {
        return response()->json([
            'data' => $inventoryTransfer->load(['sourceChapter', 'destinationChapter', 'bloodRequest', 'createdBy', 'approvedBy']),
        ]);
    }

    public function approve(Request $request, InventoryTransfer $inventoryTransfer): JsonResponse
    {
        if (! $inventoryTransfer->canBeApproved()) {
            return response()->json(['message' => 'Transfer cannot be approved in its current status.'], 422);
        }

        $validated = $request->validate([
            'units_approved' => ['required', 'integer', 'min:1', 'max:' . $inventoryTransfer->units_requested],
        ]);

        $inventoryTransfer->approve($validated['units_approved']);

        return response()->json([
            'message' => 'Transfer approved.',
            'data' => $inventoryTransfer,
        ]);
    }

    public function complete(InventoryTransfer $inventoryTransfer, InventoryManagementService $inventoryService): JsonResponse
    {
        if ($inventoryTransfer->transfer_status !== 'in_transit') {
            return response()->json(['message' => 'Only in-transit transfers can be completed.'], 422);
        }

        $success = $inventoryService->completeTransfer($inventoryTransfer);

        if (! $success) {
            return response()->json(['message' => 'Unable to complete transfer.'], 500);
        }

        return response()->json([
            'message' => 'Transfer completed successfully.',
            'data' => $inventoryTransfer->fresh()->load(['sourceChapter', 'destinationChapter']),
        ]);
    }

    public function cancel(Request $request, InventoryTransfer $inventoryTransfer): JsonResponse
    {
        if (! in_array($inventoryTransfer->transfer_status, ['pending', 'approved', 'in_transit'], true)) {
            return response()->json(['message' => 'Transfer cannot be cancelled in its current status.'], 422);
        }

        $inventoryTransfer->cancel($request->input('reason'));

        return response()->json(['message' => 'Transfer cancelled.', 'data' => $inventoryTransfer]);
    }
}
