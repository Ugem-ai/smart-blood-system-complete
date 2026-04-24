<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BloodRequest;
use App\Models\DonorRequestResponse;
use App\Models\RequestMatch;
use App\Services\BloodRequestService;
use App\Services\DonorAllocationService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DonorResponseController extends Controller
{
    public function accept(Request $request, NotificationService $notificationService, DonorAllocationService $allocationService, BloodRequestService $bloodRequestService): JsonResponse
    {
        return $this->handleResponse($request, 'accepted', $notificationService, $allocationService, $bloodRequestService);
    }

    public function decline(Request $request, NotificationService $notificationService, DonorAllocationService $allocationService, BloodRequestService $bloodRequestService): JsonResponse
    {
        return $this->handleResponse($request, 'declined', $notificationService, $allocationService, $bloodRequestService);
    }

    protected function handleResponse(Request $request, string $response, NotificationService $notificationService, DonorAllocationService $allocationService, BloodRequestService $bloodRequestService): JsonResponse
    {
        $validated = $request->validate([
            'blood_request_id' => ['required', 'integer', 'exists:blood_requests,id'],
        ]);

        $donor = $request->user()->donorProfile;

        if (! $donor) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Donor profile not found.',
            ], 404);
        }

        $bloodRequest = BloodRequest::query()->with('hospital')->findOrFail($validated['blood_request_id']);

        if ($response === 'accepted') {
            $activeAllocation = $allocationService->activeAllocationForDonor($donor->id, $bloodRequest->id);

            if ($activeAllocation) {
                $allocatedRequestId = (int) ($activeAllocation->blood_request_id ?: $activeAllocation->request_id);

                return response()->json([
                    'success' => false,
                    'message' => 'Donor is already allocated to another active hospital request.',
                    'data' => [
                        'blood_request_id' => $bloodRequest->id,
                        'donor_id' => $donor->id,
                        'allocated_request_id' => $allocatedRequestId,
                    ],
                ], 409);
            }
        }

        DonorRequestResponse::updateOrCreate(
            [
                'donor_id' => $donor->id,
                'blood_request_id' => $bloodRequest->id,
            ],
            [
                'response' => $response,
                'responded_at' => now(),
            ]
        );

        $match = RequestMatch::query()
            ->where('donor_id', $donor->id)
            ->where(function ($query) use ($bloodRequest) {
                $query->where('request_id', $bloodRequest->id)
                    ->orWhere('blood_request_id', $bloodRequest->id);
            })
            ->first();

        if ($match) {
            $match->update([
                'response_status' => $response,
            ]);
        }

        if ($response === 'accepted') {
            $allocationService->reserveDonorForRequest($donor->id, $bloodRequest);
        }

        if ($response === 'accepted' && $bloodRequest->status === 'pending') {
            $bloodRequest->update(['status' => 'matching']);
        }

        if ($bloodRequest->hospital) {
            $notificationService->sendHospitalResponseUpdate(
                $bloodRequest->hospital,
                $bloodRequest,
                $donor,
                $response
            );
        }

        $bloodRequestService->syncTrackingCounts($bloodRequest->fresh());

        return response()->json([
            'success' => true,
            'message' => 'Response recorded.',
            'data' => [
                'blood_request_id' => $bloodRequest->id,
                'donor_id' => $donor->id,
                'response' => $response,
                'request_status' => $bloodRequest->status,
                'coordination' => $allocationService->coordinationStateForDonorOnRequest($donor->id, $bloodRequest->id),
            ],
        ]);
    }
}
