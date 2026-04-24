<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\BloodRequest;
use App\Models\RequestMatch;
use App\Algorithms\PASTMatch;
use App\Services\DonorFilterService;
use App\Services\EmergencyBroadcastModeService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BloodRequestController extends Controller
{
    public function store(
        Request $request,
        DonorFilterService $donorFilterService,
        PASTMatch $pastMatch,
        EmergencyBroadcastModeService $emergencyBroadcastModeService
    ): RedirectResponse
    {
        $validated = $request->validate([
            'blood_type' => ['required', 'string', 'max:5'],
            'city' => ['required', 'string', 'max:255'],
            'units_required' => ['nullable', 'integer', 'min:1', 'max:20'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
            'requested_units' => ['nullable', 'integer', 'min:1', 'max:20'],
            'urgency_level' => ['nullable', 'in:low,medium,high'],
            'required_on' => ['nullable', 'date'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $hospital = $request->user()->hospitalProfile;
        $unitsRequired = (int) ($validated['units_required'] ?? $validated['quantity'] ?? $validated['requested_units'] ?? 1);
        $requestedUrgency = $validated['urgency_level'] ?? 'medium';
        $resolvedUrgency = $emergencyBroadcastModeService->applyPriorityUrgency($requestedUrgency);
        $resolvedDistanceLimitKm = $emergencyBroadcastModeService->applyExpandedRadius(null);

        $bloodRequest = $hospital->bloodRequests()->create([
            'hospital_name' => $hospital->hospital_name,
            'blood_type' => $validated['blood_type'],
            'units_required' => $unitsRequired,
            'quantity' => $unitsRequired,
            'requested_units' => $unitsRequired,
            'urgency_level' => $resolvedUrgency,
            'city' => $validated['city'],
            'required_on' => $validated['required_on'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'status' => 'pending',
        ]);

        $topMatches = $pastMatch->rankDonors(
            $donorFilterService->filterForRequest(
                requestedBloodType: $bloodRequest->blood_type,
                requestLatitude: $bloodRequest->latitude !== null ? (float) $bloodRequest->latitude : null,
                requestLongitude: $bloodRequest->longitude !== null ? (float) $bloodRequest->longitude : null,
                distanceLimitKm: $resolvedDistanceLimitKm,
                requestCity: $bloodRequest->city,
            ),
            ['urgency_level' => $bloodRequest->urgency_level]
        )->take(10)->values();

        foreach ($topMatches as $index => $match) {
            RequestMatch::create([
                'blood_request_id' => $bloodRequest->id,
                'request_id' => $bloodRequest->id,
                'donor_id' => $match['donor']->id,
                'score' => $match['score'],
                'response_status' => 'pending',
                'rank' => $index + 1,
            ]);
        }

        $bloodRequest->update([
            'matched_donors' => $topMatches->map(fn (array $match) => $match['donor']->name)->values()->all(),
            'status' => $topMatches->isNotEmpty() ? 'matching' : 'pending',
        ]);

        ActivityLog::record($request->user()->id, 'blood-request.created', [
            'blood_request_id' => $bloodRequest->id,
            'hospital_id' => $hospital->id,
            'units_required' => $unitsRequired,
            'requested_urgency_level' => $requestedUrgency,
            'resolved_urgency_level' => $resolvedUrgency,
            'distance_limit_km' => $resolvedDistanceLimitKm,
            'disaster_response_mode' => $emergencyBroadcastModeService->isDisasterResponseActive(),
            'matches_found' => $topMatches->count(),
        ]);

        return back()->with('status', 'blood-request-submitted');
    }

    public function updateStatus(Request $request, BloodRequest $bloodRequest, NotificationService $notificationService): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,matching,completed,cancelled'],
        ]);

        $hospital = $request->user()->hospitalProfile;
        abort_unless($bloodRequest->hospital_id === $hospital->id, 403);

        $bloodRequest->update([
            'status' => $validated['status'],
        ]);

        if (in_array($validated['status'], ['pending', 'matching'], true)) {
            $bloodRequest->matches()->with('donor')->get()->each(function (RequestMatch $match) use ($notificationService, $bloodRequest) {
                if ($match->donor) {
                    $notificationService->sendRequestReminder($match->donor, $bloodRequest);
                }
            });
        }

        ActivityLog::record($request->user()->id, 'blood-request.status-updated', [
            'blood_request_id' => $bloodRequest->id,
            'status' => $validated['status'],
        ]);

        return back()->with('status', 'blood-request-status-updated');
    }

    public function respond(Request $request, BloodRequest $bloodRequest): RedirectResponse
    {
        $validated = $request->validate([
            'response' => ['required', 'in:accepted,declined'],
        ]);

        $donor = $request->user()->donorProfile;

        $bloodRequest->donorResponses()->updateOrCreate(
            ['donor_id' => $donor->id],
            [
                'response' => $validated['response'],
                'responded_at' => now(),
            ]
        );

        if ($validated['response'] === 'accepted' && $bloodRequest->status === 'pending') {
            $bloodRequest->update(['status' => 'matching']);
        }

        ActivityLog::record($request->user()->id, 'blood-request.donor-response', [
            'blood_request_id' => $bloodRequest->id,
            'donor_id' => $donor->id,
            'response' => $validated['response'],
        ]);

        return back()->with('status', 'donor-response-recorded');
    }
}
