<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessBloodRequestMatchingJob;
use App\Models\ActivityLog;
use App\Models\BloodRequest;
use App\Models\Hospital;
use App\Models\User;
use App\Models\RequestMatch;
use App\Services\DonorAllocationService;
use App\Services\DonorFilterService;
use App\Services\EmergencyBroadcastModeService;
use App\Services\BloodRequestService;
use App\Services\HospitalInviteCodeService;
use App\Services\NotificationService;
use App\Services\TravelIntelligenceService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class HospitalController extends Controller
{
    public function createRegistration(): View
    {
        return view('app');
    }

    public function register(Request $request, HospitalInviteCodeService $inviteCodes): RedirectResponse
    {
        $validated = $request->validate([
            'hospital_name'             => ['required', 'string', 'max:255'],
            'address'                   => ['nullable', 'string', 'max:255'],
            'location'                  => ['nullable', 'string', 'max:255', 'required_without:address'],
            'contact_person'            => ['required', 'string', 'max:255'],
            'contact_number'            => ['required', 'string', 'max:30'],
            'email'                     => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password'                  => ['required', 'confirmed', Rules\Password::defaults()],
            'hospital_registration_code'=> ['nullable', 'string'],
            'invite_code'               => ['nullable', 'string'],
        ]);

        if (! $this->isAllowedHospitalDomain($validated['email'])) {
            return back()->withErrors([
                'email' => 'Hospital registration is restricted to approved institutional email domains.',
            ])->withInput();
        }

        $inviteCode       = trim((string) ($validated['invite_code'] ?? ''));
        $registrationCode = $validated['hospital_registration_code'] ?? null;

        if ($inviteCode === '' && ! is_string($registrationCode)) {
            return back()->withErrors([
                'invite_code' => 'Either invite code or hospital registration code is required.',
            ])->withInput();
        }

        if ($inviteCode !== '') {
            if (! $inviteCodes->validateAndConsume($inviteCode, $validated['email'])) {
                return back()->withErrors([
                    'invite_code' => 'Invalid, expired, revoked, or already-used hospital invite code.',
                ])->withInput();
            }
        } elseif (! $this->isValidHospitalRegistrationCode($registrationCode)) {
            return back()->withErrors([
                'hospital_registration_code' => 'Invalid hospital registration code.',
            ])->withInput();
        }

        $resolvedAddress = $validated['address'] ?? $validated['location'];

        $user = User::create([
            'name'     => $validated['contact_person'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role'     => 'hospital',
        ]);

        Hospital::create([
            'user_id'        => $user->id,
            'hospital_name'  => $validated['hospital_name'],
            'address'        => $resolvedAddress,
            'location'       => $resolvedAddress,
            'contact_person' => $validated['contact_person'],
            'contact_number' => $validated['contact_number'],
            'email'          => $validated['email'],
            'password'       => $validated['password'],
            'status'         => 'pending',
        ]);

        ActivityLog::record($user->id, 'hospital.registered', [
            'hospital_name' => $validated['hospital_name'],
            'status'        => 'pending',
        ]);

        event(new Registered($user));

        return redirect()->route('login')->with('status', 'Hospital registration submitted. Await admin approval before login.');
    }

    public function dashboard(Request $request, DonorAllocationService $allocationService): View
    {
        return view('app');
    }

    public function requestStatus(Request $request, DonorAllocationService $allocationService): View
    {
        return view('app');
    }

    public function storeRequest(Request $request, EmergencyBroadcastModeService $emergencyBroadcastModeService, BloodRequestService $bloodRequestService): RedirectResponse
    {
        $validated = $request->validate([
            'blood_type'      => ['required', 'string', 'max:5'],
            'city'            => ['required', 'string', 'max:255'],
            'units_required'  => ['nullable', 'integer', 'min:1'],
            'requested_units' => ['nullable', 'integer', 'min:1'],
            'quantity'        => ['nullable', 'integer', 'min:1'],
            'urgency_level'   => ['nullable', 'in:low,medium,high'],
            'required_on'     => ['nullable', 'date'],
            'is_emergency'    => ['sometimes', 'boolean'],
        ]);

        $units   = (int) ($validated['requested_units'] ?? $validated['units_required'] ?? $validated['quantity'] ?? 1);
        $urgency = $validated['urgency_level'] ?? 'medium';

        $hospital = $request->user()->hospitalProfile;

        $resolvedUrgency = $emergencyBroadcastModeService->applyPriorityUrgency($urgency);
        $disasterActive = $emergencyBroadcastModeService->isDisasterResponseActive();
        $isEmergency = $bloodRequestService->resolveIsEmergency(
            resolvedUrgency: $resolvedUrgency,
            requestedEmergency: (bool) ($validated['is_emergency'] ?? false),
            disasterModeActive: $disasterActive,
        );

        $bloodRequest = $hospital->bloodRequests()->create([
            'hospital_name'   => $hospital->hospital_name,
            'blood_type'      => $validated['blood_type'],
            'quantity'        => $units,
            'units_required'  => $units,
            'requested_units' => $units,
            'urgency_level'   => $resolvedUrgency,
            'city'            => $validated['city'],
            'required_on'     => $validated['required_on'] ?? null,
            'is_emergency'    => $isEmergency,
            'status'          => 'pending',
        ]);

        ProcessBloodRequestMatchingJob::dispatch(
            bloodRequestId: $bloodRequest->id,
            actorUserId:    $request->user()->id,
        )->onQueue('matching');

        ActivityLog::record($request->user()->id, 'blood-request.created', [
            'blood_request_id' => $bloodRequest->id,
            'is_emergency'     => $isEmergency,
        ]);

        return back()->with('status', 'blood-request-submitted');
    }

    public function updateRequestStatus(Request $request, BloodRequest $bloodRequest): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,matching,fulfilled,cancelled,completed,open'],
        ]);

        $bloodRequest->update(['status' => $validated['status']]);

        return back()->with('status', 'request-status-updated');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // matchedDonors — GET /hospital/requests/{bloodRequest}/matched-donors
    //
    // FIX: previously only read from request_matches (algorithm results).
    // Donors who self-respond via broadcast have a donor_responses row but
    // no request_match row, so they were invisible. We now merge both sources,
    // deduplicating by donor_id so nobody appears twice.
    // ─────────────────────────────────────────────────────────────────────────

    public function matchedDonors(
        Request $request,
        BloodRequest $bloodRequest,
        DonorFilterService $donorFilterService,
        TravelIntelligenceService $travelIntelligenceService,
        DonorAllocationService $allocationService
    ): View {
        // Eager-load both sources up front to avoid N+1 queries
        $bloodRequest->loadMissing(['matches.donor', 'donorResponses.donor']);

        // --- 1. Algorithm-matched donors (request_matches) ---
        $donorIdsSeen = [];

        $matchData = $bloodRequest->matches
            ->filter(fn ($match) => $match->donor !== null)
            ->sortBy('rank')
            ->values()
            ->map(function ($match) use (
                $bloodRequest,
                $allocationService,
                $donorFilterService,
                $travelIntelligenceService,
                &$donorIdsSeen
            ) {
                $donorIdsSeen[$match->donor_id] = true;
                $donor = $match->donor;

                return $this->buildMatchDetail(
                    donor:                     $donor,
                    bloodRequest:              $bloodRequest,
                    donorFilterService:        $donorFilterService,
                    travelIntelligenceService: $travelIntelligenceService,
                    allocationService:         $allocationService,
                    rank:                      $match->rank,
                    score:                     $match->score,
                    responseStatus:            $match->response_status,
                );
            });

        // --- 2. Self-responded donors NOT in request_matches ---
        // These donors responded via broadcast/public listing and bypassed
        // the PAST-Match algorithm, so they have no request_match row.
        $selfResponded = $bloodRequest->donorResponses
            ->filter(fn ($resp) =>
                $resp->donor !== null
                && ! isset($donorIdsSeen[$resp->donor_id])
            )
            ->values();

        $algorithmCount = $matchData->count();

        foreach ($selfResponded as $index => $resp) {
            $matchData->push(
                $this->buildMatchDetail(
                    donor:                     $resp->donor,
                    bloodRequest:              $bloodRequest,
                    donorFilterService:        $donorFilterService,
                    travelIntelligenceService: $travelIntelligenceService,
                    allocationService:         $allocationService,
                    rank:                      $algorithmCount + $index + 1,
                    score:                     null,
                    responseStatus:            $resp->response,
                )
            );
        }

        return view('hospital.matched-donors', compact('bloodRequest', 'matchData'));
    }

    public function confirmDonorAssignment(Request $request, BloodRequest $bloodRequest, NotificationService $notificationService): RedirectResponse
    {
        $hospital = $request->user()->hospitalProfile;

        abort_unless($bloodRequest->hospital_id === $hospital->id, 403);

        $bloodRequest->update([
            'status'                        => 'completed',
            'donor_assignment_confirmed_at' => now(),
        ]);

        $bloodRequest->donorResponses()
            ->where('response', 'accepted')
            ->with('donor')
            ->get()
            ->each(function ($response) use ($notificationService, $bloodRequest) {
                if ($response->donor) {
                    $notificationService->sendDonationConfirmation($response->donor, $bloodRequest);
                }
            });

        ActivityLog::record($request->user()->id, 'blood-request.confirmed', [
            'blood_request_id' => $bloodRequest->id,
        ]);

        return back()->with('status', 'donor-assignment-confirmed');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Builds a single match-detail array for the Blade view.
     * Shared between algorithm-matched and self-responded donors so the
     * view always receives an identical shape regardless of origin.
     */
    private function buildMatchDetail(
        \App\Models\Donor $donor,
        BloodRequest $bloodRequest,
        DonorFilterService $donorFilterService,
        TravelIntelligenceService $travelIntelligenceService,
        DonorAllocationService $allocationService,
        int $rank,
        ?float $score,
        ?string $responseStatus,
    ): array {
        $coordination = $allocationService->coordinationStateForDonorOnRequest($donor->id, $bloodRequest->id);

        $distanceKm = null;
        if (
            $bloodRequest->latitude !== null && $bloodRequest->longitude !== null
            && $donor->latitude     !== null && $donor->longitude        !== null
        ) {
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

        $coordinationLabel = match ($coordination['coordination_status']) {
            'reserved_here'      => 'Reserved here',
            'reserved_elsewhere' => 'Reserved elsewhere',
            default              => 'Available',
        };

        return [
            'donor'               => $donor,
            'rank'                => $rank,
            'score'               => $score,
            'response_status'     => $responseStatus,
            'coordination_status' => $coordination['coordination_status'],
            'coordination_label'  => $coordinationLabel,
            'distance_km'         => $distanceKm,
            'travel'              => $travel,
        ];
    }

    private function hydrateCoordinationMatches(EloquentCollection $bloodRequests, DonorAllocationService $allocationService): EloquentCollection
    {
        return $bloodRequests->each(function (BloodRequest $bloodRequest) use ($allocationService) {
            $coordinationMatches = $this->buildMatchDetails($bloodRequest, $allocationService);

            $bloodRequest->setAttribute('coordination_matches', $coordinationMatches);
        });
    }

    private function buildMatchDetails(
        BloodRequest $bloodRequest,
        DonorAllocationService $allocationService,
        ?DonorFilterService $donorFilterService = null,
        ?TravelIntelligenceService $travelIntelligenceService = null
    ): array {
        return $bloodRequest->matches
            ->filter(fn (RequestMatch $match) => $match->donor !== null)
            ->sortBy('rank')
            ->values()
            ->map(function (RequestMatch $match) use ($bloodRequest, $allocationService, $donorFilterService, $travelIntelligenceService) {
                $coordination = $allocationService->coordinationStateForDonorOnRequest($match->donor_id, $bloodRequest->id);

                $distanceKm = null;
                if (
                    $donorFilterService !== null
                    && $bloodRequest->latitude !== null
                    && $bloodRequest->longitude !== null
                    && $match->donor->latitude !== null
                    && $match->donor->longitude !== null
                ) {
                    $distanceKm = $donorFilterService->haversineDistanceKm(
                        (float) $bloodRequest->latitude,
                        (float) $bloodRequest->longitude,
                        (float) $match->donor->latitude,
                        (float) $match->donor->longitude,
                    );
                }

                $travel = $travelIntelligenceService !== null
                    ? $travelIntelligenceService->analyze(
                        distanceKm:     $distanceKm,
                        requestCity:    $bloodRequest->city,
                        donorCity:      $match->donor->city,
                        hasCoordinates: $match->donor->latitude !== null && $match->donor->longitude !== null,
                    )
                    : null;

                return [
                    'donor_id'                       => $match->donor_id,
                    'name'                           => $match->donor->name,
                    'blood_type'                     => $match->donor->blood_type,
                    'city'                           => $match->donor->city,
                    'contact_number'                 => $match->donor->contact_number,
                    'email'                          => $match->donor->email,
                    'availability'                   => (bool) $match->donor->availability,
                    'rank'                           => $match->rank,
                    'score'                          => $match->score,
                    'response_status'                => $match->response_status,
                    'coordination_status'            => $coordination['coordination_status'],
                    'allocated_request_id'           => $coordination['allocated_request_id'],
                    'distance_km'                    => $distanceKm,
                    'estimated_travel_minutes'       => $travel['estimated_travel_minutes'] ?? null,
                    'traffic_condition'              => $travel['traffic_condition'] ?? null,
                    'traffic_multiplier'             => $travel['traffic_multiplier'] ?? null,
                    'transport_accessibility_score'  => $travel['transport_accessibility_score'] ?? null,
                    'fastest_arrival_score'          => $travel['fastest_arrival_score'] ?? null,
                ];
            })
            ->all();
    }

    private function isAllowedHospitalDomain(string $email): bool
    {
        $allowedDomains = collect(explode(',', (string) env('HOSPITAL_EMAIL_DOMAINS', '')))
            ->map(fn ($domain) => Str::lower(trim($domain)))
            ->filter()
            ->values()
            ->all();

        if (empty($allowedDomains)) {
            return true;
        }

        $emailDomain = Str::lower(Str::after((string) $email, '@'));

        return in_array($emailDomain, $allowedDomains, true);
    }

    private function isValidHospitalRegistrationCode(?string $code): bool
    {
        $expectedCode = (string) env('HOSPITAL_REGISTRATION_CODE', '');

        if ($expectedCode === '') {
            return false;
        }

        if (! is_string($code) || $code === '') {
            return false;
        }

        return hash_equals($expectedCode, $code);
    }
}