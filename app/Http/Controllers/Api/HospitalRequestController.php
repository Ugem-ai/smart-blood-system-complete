<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBloodRequestRequest;
use App\Jobs\ProcessBloodRequestMatchingJob;
use App\Models\ActivityLog;
use App\Models\DonationHistory;
use App\Models\Donor;
use App\Models\BloodRequest;
use App\Models\RequestMatch;
use App\Services\BloodRequestService;
use App\Services\DonorAllocationService;
use App\Services\DonorFilterService;
use App\Services\EmergencyBroadcastModeService;
use App\Services\NotificationService;
use App\Services\TravelIntelligenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HospitalRequestController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // index — GET /api/hospital/requests
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $hospital = $request->user()->hospitalProfile;

        if (! $hospital) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Hospital profile not found.',
            ], 404);
        }

        $perPage = min(100, max(5, (int) $request->integer('per_page', 15)));

        $query = $hospital->bloodRequests()->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('urgency_level')) {
            $query->where('urgency_level', $request->input('urgency_level'));
        }
        if ($request->boolean('is_emergency')) {
            $query->where('is_emergency', true);
        }
        if ($request->filled('component')) {
            $query->where('component', $request->input('component'));
        }

        $requests = $query->paginate($perPage);

        ActivityLog::record($request->user()->id, 'hospital.request-list.accessed', [
            'hospital_id' => $hospital->id,
            'filters'     => $request->only('status', 'urgency_level', 'is_emergency', 'component'),
            'per_page'    => $perPage,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $requests,
            'message' => 'Blood requests retrieved successfully.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // store — POST /api/hospital/requests
    // ─────────────────────────────────────────────────────────────────────────

    public function store(
        StoreBloodRequestRequest      $request,
        EmergencyBroadcastModeService $emergencyBroadcastModeService,
        BloodRequestService           $bloodRequestService
    ): JsonResponse {
        $validated = $request->validated();

        $hospital = $request->user()->hospitalProfile;

        if (! $hospital) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Hospital profile not found.',
            ], 404);
        }

        if ($hospital->status !== 'approved') {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Hospital is not approved for request submission.',
            ], 403);
        }

        $disasterState  = $emergencyBroadcastModeService->disasterResponseState();
        $disasterActive = (bool) ($disasterState['active'] ?? false);

        $requestedUrgency   = $validated['urgency_level'];
        $resolvedUrgency    = $emergencyBroadcastModeService->applyPriorityUrgency($requestedUrgency);
        $resolvedDistanceKm = $emergencyBroadcastModeService->applyExpandedRadius(
            (int) $validated['distance_limit_km']
        );

        $isEmergency = $bloodRequestService->resolveIsEmergency(
            resolvedUrgency:    $resolvedUrgency,
            requestedEmergency: (bool) ($validated['is_emergency'] ?? false),
            disasterModeActive: $disasterActive,
        );

        $units = (int) $validated['units_required'];

        $bloodRequest = $hospital->bloodRequests()->create([
            'hospital_name'     => $hospital->hospital_name,
            'contact_person'    => $validated['contact_person'] ?? null,
            'contact_number'    => $validated['contact_number'] ?? null,
            'blood_type'        => $validated['blood_type'],
            'component'         => $validated['component'] ?? null,
            'reason'            => $validated['reason'] ?? null,
            'units_required'    => $units,
            'quantity'          => $units,
            'requested_units'   => $units,
            'urgency_level'     => $resolvedUrgency,
            'city'              => $validated['city'],
            'province'          => $validated['province'] ?? null,
            'latitude'          => $validated['latitude'] ?? null,
            'longitude'         => $validated['longitude'] ?? null,
            'distance_limit_km' => (float) $resolvedDistanceKm,
            'required_on'       => $validated['required_on'] ?? null,
            'expiry_time'       => $validated['expiry_time'] ?? null,
            'is_emergency'      => $isEmergency,
            'status'            => 'pending',
        ]);

        ProcessBloodRequestMatchingJob::dispatch(
            bloodRequestId:  $bloodRequest->id,
            actorUserId:     $request->user()->id,
            distanceLimitKm: $resolvedDistanceKm,
        )->onQueue('matching');

        ActivityLog::record($request->user()->id, 'blood-request.created', [
            'blood_request_id'        => $bloodRequest->id,
            'case_id'                 => $bloodRequest->case_id,
            'hospital_id'             => $hospital->id,
            'units_required'          => $units,
            'component'               => $bloodRequest->component,
            'reason'                  => $bloodRequest->reason,
            'requested_urgency_level' => $requestedUrgency,
            'resolved_urgency_level'  => $resolvedUrgency,
            'distance_limit_km'       => $resolvedDistanceKm,
            'is_emergency'            => $isEmergency,
            'disaster_response_mode'  => $disasterActive,
            'matching_job_dispatched' => true,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $bloodRequest->fresh(),
            'message' => 'Blood request submitted successfully.',
            'operational_mode' => [
                'disaster_response_active' => $disasterActive,
                'priority_request_applied' => $resolvedUrgency !== $requestedUrgency,
                'expanded_radius_km'       => $resolvedDistanceKm,
                'mass_notification'        => $disasterActive,
                'is_emergency'             => $isEmergency,
            ],
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // show — GET /api/hospital/requests/{bloodRequest}
    // ─────────────────────────────────────────────────────────────────────────

    public function show(Request $request, BloodRequest $bloodRequest): JsonResponse
    {
        $hospital = $request->user()->hospitalProfile;

        if (! $hospital) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Hospital profile not found.',
            ], 404);
        }

        if ((int) $bloodRequest->hospital_id !== (int) $hospital->id) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Unauthorized: this request does not belong to your hospital.',
            ], 403);
        }

        $bloodRequest->load([
            'hospital',
            'matches' => fn ($q) => $q->orderBy('rank'),
        ]);

        ActivityLog::record($request->user()->id, 'hospital.request.viewed', [
            'hospital_id'      => $hospital->id,
            'blood_request_id' => $bloodRequest->id,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $bloodRequest,
            'message' => 'Blood request retrieved successfully.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // list — legacy alias kept for backward compatibility
    // ─────────────────────────────────────────────────────────────────────────

    public function list(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // update — PUT/PATCH /api/hospital/requests/{bloodRequest}
    // ─────────────────────────────────────────────────────────────────────────

    public function update(Request $request, BloodRequest $bloodRequest, BloodRequestService $bloodRequestService): JsonResponse
    {
        $validated = $request->validate([
            'status'            => ['sometimes', 'required', 'in:pending,matching,completed,fulfilled,cancelled'],
            'urgency_level'     => ['sometimes', 'required', 'in:low,medium,high,critical'],
            'required_on'       => ['sometimes', 'nullable', 'date'],
            'expiry_time'       => ['sometimes', 'nullable', 'date'],
            'distance_limit_km' => ['sometimes', 'nullable', 'numeric', 'min:1', 'max:500'],
            'is_emergency'      => ['sometimes', 'boolean'],
        ]);

        $hospital = $request->user()->hospitalProfile;

        if (! $hospital) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Hospital profile not found.',
            ], 404);
        }

        if ((int) $bloodRequest->hospital_id !== (int) $hospital->id) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Unauthorized request scope.',
            ], 403);
        }

        if (array_key_exists('status', $validated)) {
            $transitionError = $bloodRequestService->invalidTransitionReason($bloodRequest, $validated['status']);

            if ($transitionError !== null) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => $transitionError,
                ], 422);
            }
        }

        $updates = [];
        foreach (['status', 'urgency_level', 'required_on', 'expiry_time', 'is_emergency', 'distance_limit_km'] as $field) {
            if (array_key_exists($field, $validated)) {
                $updates[$field] = $validated[$field];
            }
        }

        if ($updates !== []) {
            $bloodRequest->update($updates);
        }

        $shouldRerunMatching = collect(['urgency_level', 'distance_limit_km', 'is_emergency'])
            ->contains(fn ($field) => array_key_exists($field, $updates));

        if ($shouldRerunMatching && ! in_array($bloodRequest->status, ['fulfilled', 'cancelled'], true)) {
            if (($updates['status'] ?? null) === null && $bloodRequest->status === 'pending') {
                $bloodRequest->update(['status' => 'matching']);
            }

            ProcessBloodRequestMatchingJob::dispatch(
                bloodRequestId: $bloodRequest->id,
                actorUserId: $request->user()->id,
                distanceLimitKm: (float) ($bloodRequest->distance_limit_km ?? 0),
            )->onQueue('matching');
        }

        ActivityLog::record($request->user()?->id, 'hospital.request.updated', [
            'hospital_id'      => $hospital->id,
            'blood_request_id' => $bloodRequest->id,
            'changes'          => $updates,
            'matching_job_dispatched' => $shouldRerunMatching,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $bloodRequest->fresh(),
            'message' => 'Blood request updated successfully.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // matchedDonors — GET /api/hospital/requests/{bloodRequest}/matched-donors
    // ─────────────────────────────────────────────────────────────────────────

    public function matchedDonors(
        Request $request,
        BloodRequest $bloodRequest,
        DonorFilterService $donorFilterService,
        TravelIntelligenceService $travelIntelligenceService,
        DonorAllocationService $allocationService
    ): JsonResponse {
        $hospital = $request->user()->hospitalProfile;

        if (! $hospital) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Hospital profile not found.',
            ], 404);
        }

        if ((int) $bloodRequest->hospital_id !== (int) $hospital->id) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Unauthorized request scope.',
            ], 403);
        }

        $bloodRequest->loadMissing('donorResponses');

        $rows = RequestMatch::query()
            ->where(function ($query) use ($bloodRequest) {
                $query->where('request_id', $bloodRequest->id)
                    ->orWhere('blood_request_id', $bloodRequest->id);
            })
            ->with('donor')
            ->orderBy('rank')
            ->get();

        $donors = $rows
            ->filter(fn (RequestMatch $match) => $match->donor !== null)
            ->map(function (RequestMatch $match) use ($bloodRequest, $donorFilterService, $travelIntelligenceService, $allocationService) {
                $donor = $match->donor;
                $response = $bloodRequest->donorResponses->firstWhere('donor_id', $donor->id);

                $distanceKm = null;
                if ($bloodRequest->latitude !== null && $bloodRequest->longitude !== null && $donor->latitude !== null && $donor->longitude !== null) {
                    $distanceKm = $donorFilterService->haversineDistanceKm(
                        (float) $bloodRequest->latitude,
                        (float) $bloodRequest->longitude,
                        (float) $donor->latitude,
                        (float) $donor->longitude,
                    );
                }

                $travel = $travelIntelligenceService->analyze(
                    distanceKm:     $distanceKm,
                    requestCity:    $bloodRequest->city,
                    donorCity:      $donor->city,
                    hasCoordinates: $donor->latitude !== null && $donor->longitude !== null,
                );

                $coordination = $allocationService->coordinationStateForDonorOnRequest($donor->id, $bloodRequest->id);

                return [
                    'donor_id'                      => $donor->id,
                    'name'                          => $donor->name,
                    'blood_type'                    => $donor->blood_type,
                    'contact_number'                => $donor->contact_number,
                    'email'                         => $donor->email,
                    'city'                          => $donor->city,
                    'latitude'                      => $donor->latitude,
                    'longitude'                     => $donor->longitude,
                    'availability'                  => (bool) $donor->availability,
                    'reliability_score'             => (float) $donor->reliability_score,
                    'score'                         => $match->score,
                    'rank'                          => $match->rank,
                    'response_status'               => $match->response_status,
                    'responded_at'                  => optional($response?->responded_at)?->toISOString(),
                    'distance_km'                   => $distanceKm,
                    'estimated_travel_minutes'      => $travel['estimated_travel_minutes'],
                    'traffic_condition'             => $travel['traffic_condition'],
                    'traffic_multiplier'            => $travel['traffic_multiplier'],
                    'transport_accessibility_score' => $travel['transport_accessibility_score'],
                    'fastest_arrival_score'         => $travel['fastest_arrival_score'],
                    'coordination_status'           => $coordination['coordination_status'],
                    'allocated_request_id'          => $coordination['allocated_request_id'],
                ];
            })
            ->values();

        ActivityLog::record($request->user()->id, 'hospital.matched-donors.accessed', [
            'hospital_id'      => $hospital->id,
            'blood_request_id' => $bloodRequest->id,
            'result_count'     => $donors->count(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'blood_request_id' => $bloodRequest->id,
                'donors'           => $donors,
            ],
            'message' => 'Matched donors retrieved successfully.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // confirmDonation — POST /api/hospital/confirm-donation
    // ─────────────────────────────────────────────────────────────────────────

    public function confirmDonation(Request $request, NotificationService $notificationService, BloodRequestService $bloodRequestService): JsonResponse
    {
        $validated = $request->validate([
            'blood_request_id' => ['required', 'integer', 'exists:blood_requests,id'],
            'donor_id'         => ['required', 'integer', 'exists:donors,id'],
            'units'            => ['nullable', 'integer', 'min:1', 'max:20'],
            'donation_date'    => ['nullable', 'date'],
        ]);

        $hospital = $request->user()->hospitalProfile;

        if (! $hospital) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Hospital profile not found.',
            ], 404);
        }

        $bloodRequest = $hospital->bloodRequests()->find($validated['blood_request_id']);

        if (! $bloodRequest) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Blood request not found for this hospital.',
            ], 404);
        }

        $donor = Donor::query()->findOrFail($validated['donor_id']);

        $acceptedResponseExists = $bloodRequest->donorResponses()
            ->where('donor_id', $donor->id)
            ->where('response', 'accepted')
            ->exists();

        if (! $acceptedResponseExists) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Donor has not accepted this request.',
            ], 422);
        }

        $donationDate = $validated['donation_date'] ?? now()->toDateString();
        $units        = (int) ($validated['units'] ?? $bloodRequest->units_required ?? 1);

        $donation = DonationHistory::create([
            'donor_id'      => $donor->id,
            'hospital_id'   => $hospital->id,
            'request_id'    => $bloodRequest->id,
            'donated_at'    => $donationDate,
            'donation_date' => $donationDate,
            'location'      => $hospital->address ?? $hospital->location,
            'units'         => $units,
            'status'        => 'completed',
            'notes'         => 'Confirmed via hospital API endpoint.',
        ]);

        $donor->update([
            'last_donation_date' => $donationDate,
            'reliability_score'  => min(100, (float) $donor->reliability_score + 5),
            'availability'       => false,
        ]);

        $bloodRequest->update([
            'status'                        => 'fulfilled',
            'fulfilled_units'               => $bloodRequest->fulfilled_units + $units,
            'donor_assignment_confirmed_at' => now(),
        ]);

        RequestMatch::query()
            ->where('donor_id', $donor->id)
            ->where(function ($query) use ($bloodRequest) {
                $query->where('request_id', $bloodRequest->id)
                    ->orWhere('blood_request_id', $bloodRequest->id);
            })
            ->update(['response_status' => 'accepted']);

        $notificationService->sendDonationConfirmation($donor, $bloodRequest);

        ActivityLog::record($request->user()->id, 'donation.confirmed', [
            'blood_request_id' => $bloodRequest->id,
            'hospital_id'      => $hospital->id,
            'donor_id'         => $donor->id,
            'donation_id'      => $donation->id,
            'units'            => $units,
        ]);

        $bloodRequestService->syncTrackingCounts($bloodRequest->fresh());

        return response()->json([
            'success' => true,
            'data'    => [
                'donation_id'             => $donation->id,
                'blood_request_id'        => $bloodRequest->id,
                'donor_id'                => $donor->id,
                'donation_date'           => $donationDate,
                'units'                   => $units,
                'request_status'          => $bloodRequest->fresh()->status,
                'donor_reliability_score' => $donor->reliability_score,
            ],
            'message' => 'Donation confirmed successfully.',
        ]);
    }
}
