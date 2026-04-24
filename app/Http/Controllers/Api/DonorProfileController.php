<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BloodRequest;
use App\Models\ActivityLog;
use App\Models\Donor;
use App\Models\Hospital;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DonorProfileController extends Controller
{
    private const DEFAULT_DONOR_SETTINGS = [
        'smsAlerts' => true,
        'emailAlerts' => true,
        'urgentOnly' => false,
        'availabilityReminders' => true,
        'defaultRequestFilter' => 'all',
        'maxRadius' => '25',
        'showMissionSummary' => true,
    ];

    public function dashboard(Request $request): JsonResponse
    {
        $donor = $this->resolveDonorProfile($request);
        $totalRequestsReceived = $donor->requestResponses()->count();
        $respondedRequests = $donor->requestResponses()->whereNotNull('response')->count();
        $acceptedRequests = $donor->requestResponses()->where('response', 'accepted')->count();
        $totalDonations = $donor->donationHistories()->count();
        $responseRate = $totalRequestsReceived > 0 ? round(($respondedRequests / $totalRequestsReceived) * 100, 1) : 0.0;
        $completionRate = $acceptedRequests > 0 ? round(($totalDonations / $acceptedRequests) * 100, 1) : 0.0;

        $incomingRequests = BloodRequest::query()
            ->whereIn('status', ['pending', 'matching'])
            ->where('blood_type', $donor->blood_type)
            ->where('city', $donor->city)
            ->with([
                'hospital:id,hospital_name,address,location',
                'donorResponses' => fn ($query) => $query->where('donor_id', $donor->id),
            ])
            ->latest()
            ->take(10)
            ->get()
            ->map(function (BloodRequest $bloodRequest) use ($donor) {
                $response = $bloodRequest->donorResponses->first();
                $distanceKm = $this->distanceToHospital($donor, $bloodRequest->hospital);

                return [
                    'id' => $bloodRequest->id,
                    'hospital_name' => $bloodRequest->hospital_name,
                    'blood_type' => $bloodRequest->blood_type,
                    'units_required' => $bloodRequest->units_required,
                    'urgency_level' => $bloodRequest->urgency_level,
                    'city' => $bloodRequest->city,
                    'required_on' => optional($bloodRequest->required_on)?->toDateString(),
                    'status' => $bloodRequest->status,
                    'created_at' => optional($bloodRequest->created_at)?->toISOString(),
                    'response' => $response?->response,
                    'responded_at' => optional($response?->responded_at)?->toISOString(),
                    'distance_km' => $distanceKm,
                    'distance_limit_km' => $bloodRequest->distance_limit_km !== null ? round((float) $bloodRequest->distance_limit_km, 1) : null,
                    'hospital' => [
                        'name' => $bloodRequest->hospital?->hospital_name,
                        'address' => $bloodRequest->hospital?->address ?? $bloodRequest->hospital?->location,
                        'latitude' => $bloodRequest->hospital?->latitude !== null ? (float) $bloodRequest->hospital?->latitude : null,
                        'longitude' => $bloodRequest->hospital?->longitude !== null ? (float) $bloodRequest->hospital?->longitude : null,
                    ],
                ];
            })
            ->values();

        $donationHistory = $donor->donationHistories()
            ->with(['hospital:id,hospital_name,address,location'])
            ->latest('donated_at')
            ->take(10)
            ->get()
            ->map(function ($donation) use ($donor) {
                return [
                    'id' => $donation->id,
                    'donated_at' => optional($donation->donated_at)?->toISOString(),
                    'donation_date' => optional($donation->donation_date)?->toDateString(),
                    'units' => $donation->units,
                    'status' => $donation->status ?: 'Completed',
                    'location' => $donation->location,
                    'hospital_name' => $donation->hospital?->hospital_name,
                    'blood_type' => $donor->blood_type,
                ];
            })
            ->values();

        $responseHistory = $donor->requestResponses()
            ->with(['bloodRequest.hospital:id,hospital_name,address,location'])
            ->whereNotNull('response')
            ->latest('responded_at')
            ->take(10)
            ->get()
            ->map(function ($response) {
                $bloodRequest = $response->bloodRequest;

                return [
                    'id' => $response->id,
                    'response' => $response->response,
                    'responded_at' => optional($response->responded_at)?->toISOString(),
                    'blood_request_id' => $response->blood_request_id,
                    'hospital_name' => $bloodRequest?->hospital_name ?? $bloodRequest?->hospital?->hospital_name,
                    'hospital_address' => $bloodRequest?->hospital?->address ?? $bloodRequest?->hospital?->location,
                    'blood_type' => $bloodRequest?->blood_type,
                    'urgency_level' => $bloodRequest?->urgency_level,
                    'city' => $bloodRequest?->city,
                    'request_status' => $bloodRequest?->status,
                ];
            })
            ->values();

        $pendingResponses = $incomingRequests->where('response', null)->count();

        ActivityLog::record($request->user()->id, 'donor.dashboard.accessed', [
            'donor_id' => $donor->id,
            'incoming_requests' => $incomingRequests->count(),
        ]);

        return response()->json([
            'data' => [
                'profile' => [
                    'id' => $donor->id,
                    'name' => $donor->name,
                    'blood_type' => $donor->blood_type,
                    'city' => $donor->city,
                    'availability' => (bool) $donor->availability,
                    'last_donation_date' => optional($donor->last_donation_date)?->toDateString(),
                    'reliability_score' => (float) $donor->reliability_score,
                    'reliability_label' => $donor->reliabilityLabel(),
                    'response_rate' => $responseRate,
                    'completion_rate' => $completionRate,
                    'privacy_consent_at' => optional($donor->privacy_consent_at)?->toISOString(),
                ],
                'eligibility' => [
                    'is_eligible' => $donor->isEligibleForDonation(),
                    'minimum_interval_days' => 56,
                    'days_since_last_donation' => $donor->daysSinceLastDonation(),
                    'next_eligible_date' => optional($donor->nextEligibleDonationDate())?->toDateString(),
                    'last_screening_result' => $donor->isEligibleForDonation() ? 'Cleared for donation interval' : 'Rest interval active',
                ],
                'stats' => [
                    'incoming_requests' => $incomingRequests->count(),
                    'pending_responses' => $pendingResponses,
                    'total_donations' => $totalDonations,
                    'donations_this_year' => $donor->donationHistories()->whereYear('donated_at', now()->year)->count(),
                    'lives_saved_estimate' => $totalDonations * 3,
                    'response_rate' => $responseRate,
                    'completion_rate' => $completionRate,
                    'accepted_requests' => $acceptedRequests,
                ],
                'incoming_requests' => $incomingRequests,
                'donation_history' => $donationHistory,
                'response_history' => $responseHistory,
                'settings' => $this->normalizedDonorSettings($donor->donor_preferences),
            ],
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        $donor = $this->resolveDonorProfile($request);

        ActivityLog::record($request->user()->id, 'donor.profile.accessed', [
            'donor_id' => $donor->id,
        ]);

        return response()->json([
            'data' => $this->profilePayload($donor),
        ]);
    }

    public function settings(Request $request): JsonResponse
    {
        $donor = $this->resolveDonorProfile($request);

        ActivityLog::record($request->user()->id, 'donor.settings.accessed', [
            'donor_id' => $donor->id,
        ]);

        return response()->json([
            'data' => $this->normalizedDonorSettings($donor->donor_preferences),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'smsAlerts' => ['sometimes', 'boolean'],
            'emailAlerts' => ['sometimes', 'boolean'],
            'urgentOnly' => ['sometimes', 'boolean'],
            'availabilityReminders' => ['sometimes', 'boolean'],
            'defaultRequestFilter' => ['sometimes', 'string', 'in:all,critical,unresponded'],
            'maxRadius' => ['sometimes', 'string', 'in:10,25,50,all'],
            'showMissionSummary' => ['sometimes', 'boolean'],
        ]);

        $donor = $this->resolveDonorProfile($request);
        $settings = $this->normalizedDonorSettings(array_merge(
            $this->normalizedDonorSettings($donor->donor_preferences),
            $validated
        ));

        $donor->update([
            'donor_preferences' => $settings,
        ]);

        ActivityLog::record($request->user()->id, 'donor.settings.updated', [
            'donor_id' => $donor->id,
            'fields' => array_keys($validated),
        ]);

        return response()->json([
            'message' => 'Donor settings updated.',
            'data' => $settings,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($request->user()->id),
                Rule::unique('donors', 'email')->ignore($request->user()->donorProfile?->id),
            ],
            'blood_type' => ['sometimes', 'required', 'string', 'max:5'],
            'city' => ['sometimes', 'required', 'string', 'max:255'],
            'contact_number' => ['sometimes', 'required', 'string', 'max:30'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'last_donation_date' => ['sometimes', 'nullable', 'date'],
        ]);

        $user = $request->user();
        $donor = $this->resolveDonorProfile($request);

        if (array_key_exists('name', $validated)) {
            $user->update(['name' => $validated['name']]);
            $donor->name = $validated['name'];
        }

        if (array_key_exists('email', $validated)) {
            $user->update(['email' => $validated['email']]);
            $donor->email = $validated['email'];
        }

        $donor->fill($validated);

        // Keep phone/contact_number mirrored for backwards compatibility.
        if (array_key_exists('phone', $validated) && ! array_key_exists('contact_number', $validated)) {
            $donor->contact_number = $validated['phone'] ?? $donor->contact_number;
        }
        if (array_key_exists('contact_number', $validated) && ! array_key_exists('phone', $validated)) {
            $donor->phone = $validated['contact_number'];
        }

        $donor->save();

        ActivityLog::record($request->user()->id, 'donor.profile.updated', [
            'donor_id' => $donor->id,
            'fields' => array_keys($validated),
        ]);

        return response()->json([
            'message' => 'Donor profile updated.',
            'data' => $this->profilePayload($donor->fresh()),
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'availability' => ['sometimes', 'boolean'],
        ]);

        $donor = $this->resolveDonorProfile($request);

        $nextValue = array_key_exists('availability', $validated)
            ? (bool) $validated['availability']
            : ! (bool) $donor->availability;

        $donor->update(['availability' => $nextValue]);

        ActivityLog::record($request->user()->id, 'donor.availability.updated', [
            'donor_id' => $donor->id,
            'availability' => (bool) $donor->availability,
        ]);

        return response()->json([
            'message' => 'Donor availability updated.',
            'data' => [
                'availability' => (bool) $donor->availability,
            ],
        ]);
    }

    protected function resolveDonorProfile(Request $request): Donor
    {
        $user = $request->user();

        return $user->donorProfile()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'name' => $user->name,
                'blood_type' => 'UNK',
                'city' => 'Unknown',
                'contact_number' => 'N/A',
                'phone' => 'N/A',
                'email' => $user->email,
                'password' => 'password',
                'availability' => true,
                'reliability_score' => 0,
            ]
        );
    }

    private function distanceToHospital(Donor $donor, ?Hospital $hospital): ?float
    {
        if (! $hospital) {
            return null;
        }

        if (
            $donor->latitude === null ||
            $donor->longitude === null ||
            $hospital->latitude === null ||
            $hospital->longitude === null
        ) {
            return null;
        }

        $earthRadiusKm = 6371;
        $latFrom = deg2rad((float) $donor->latitude);
        $lonFrom = deg2rad((float) $donor->longitude);
        $latTo = deg2rad((float) $hospital->latitude);
        $lonTo = deg2rad((float) $hospital->longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(
            pow(sin($latDelta / 2), 2)
            + cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)
        ));

        return round($earthRadiusKm * $angle, 1);
    }

    private function normalizedDonorSettings(null|array|string $settings): array
    {
        if (is_string($settings)) {
            $decoded = json_decode($settings, true);
            $settings = is_array($decoded) ? $decoded : [];
        }

        $settings = is_array($settings) ? $settings : [];

        return [
            'smsAlerts' => array_key_exists('smsAlerts', $settings) ? (bool) $settings['smsAlerts'] : self::DEFAULT_DONOR_SETTINGS['smsAlerts'],
            'emailAlerts' => array_key_exists('emailAlerts', $settings) ? (bool) $settings['emailAlerts'] : self::DEFAULT_DONOR_SETTINGS['emailAlerts'],
            'urgentOnly' => array_key_exists('urgentOnly', $settings) ? (bool) $settings['urgentOnly'] : self::DEFAULT_DONOR_SETTINGS['urgentOnly'],
            'availabilityReminders' => array_key_exists('availabilityReminders', $settings) ? (bool) $settings['availabilityReminders'] : self::DEFAULT_DONOR_SETTINGS['availabilityReminders'],
            'defaultRequestFilter' => in_array($settings['defaultRequestFilter'] ?? null, ['all', 'critical', 'unresponded'], true)
                ? $settings['defaultRequestFilter']
                : self::DEFAULT_DONOR_SETTINGS['defaultRequestFilter'],
            'maxRadius' => in_array((string) ($settings['maxRadius'] ?? ''), ['10', '25', '50', 'all'], true)
                ? (string) $settings['maxRadius']
                : self::DEFAULT_DONOR_SETTINGS['maxRadius'],
            'showMissionSummary' => array_key_exists('showMissionSummary', $settings) ? (bool) $settings['showMissionSummary'] : self::DEFAULT_DONOR_SETTINGS['showMissionSummary'],
        ];
    }

    private function profilePayload(Donor $donor): array
    {
        $totalRequestsReceived = $donor->requestResponses()->count();
        $respondedRequests = $donor->requestResponses()->whereNotNull('response')->count();
        $acceptedRequests = $donor->requestResponses()->where('response', 'accepted')->count();
        $totalDonations = $donor->donationHistories()->count();

        $responseHistory = $donor->requestResponses()
            ->with(['bloodRequest.hospital:id,hospital_name,address,location'])
            ->whereNotNull('response')
            ->latest('responded_at')
            ->take(8)
            ->get()
            ->map(function ($response) {
                $bloodRequest = $response->bloodRequest;

                return [
                    'id' => $response->id,
                    'response' => $response->response,
                    'responded_at' => optional($response->responded_at)?->toISOString(),
                    'blood_request_id' => $response->blood_request_id,
                    'hospital_name' => $bloodRequest?->hospital_name ?? $bloodRequest?->hospital?->hospital_name,
                    'blood_type' => $bloodRequest?->blood_type,
                    'urgency_level' => $bloodRequest?->urgency_level,
                    'city' => $bloodRequest?->city,
                    'request_status' => $bloodRequest?->status,
                ];
            })
            ->values();

        return [
            'id' => $donor->id,
            'user_id' => $donor->user_id,
            'name' => $donor->name,
            'email' => $donor->user?->email ?? $donor->email,
            'blood_type' => $donor->blood_type,
            'city' => $donor->city,
            'phone' => $donor->phone ?? $donor->contact_number,
            'contact_number' => $donor->contact_number ?? $donor->phone,
            'latitude' => $donor->latitude !== null ? (float) $donor->latitude : null,
            'longitude' => $donor->longitude !== null ? (float) $donor->longitude : null,
            'last_donation_date' => optional($donor->last_donation_date)?->toDateString(),
            'availability' => (bool) $donor->availability,
            'reliability_score' => (float) $donor->reliability_score,
            'reliability_label' => $donor->reliabilityLabel(),
            'response_rate' => $totalRequestsReceived > 0 ? round(($respondedRequests / $totalRequestsReceived) * 100, 1) : 0.0,
            'completion_rate' => $acceptedRequests > 0 ? round(($totalDonations / $acceptedRequests) * 100, 1) : 0.0,
            'privacy_consent_at' => optional($donor->privacy_consent_at)?->toISOString(),
            'donation_eligibility' => [
                'is_eligible' => $donor->isEligibleForDonation(),
                'minimum_interval_days' => 56,
                'days_since_last_donation' => $donor->daysSinceLastDonation(),
                'next_eligible_date' => optional($donor->nextEligibleDonationDate())?->toDateString(),
                'last_screening_result' => $donor->isEligibleForDonation() ? 'Cleared for donation interval' : 'Rest interval active',
            ],
            'response_history' => $responseHistory,
            'settings' => $this->normalizedDonorSettings($donor->donor_preferences),
        ];
    }
}
