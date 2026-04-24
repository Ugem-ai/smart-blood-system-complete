<?php

namespace App\Http\Controllers\Api;

use App\Algorithms\PASTMatch;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessBloodRequestMatchingJob;
use App\Jobs\SendEmergencyNotificationsJob;
use App\Models\ActivityLog;
use App\Models\BloodRequest;
use App\Models\Donor;
use App\Models\DonorAlertLog;
use App\Models\NationalPartnerSyncLog;
use App\Models\DonorRequestResponse;
use App\Models\HospitalInviteCode;
use App\Models\NotificationDelivery;
use App\Models\RequestMatch;
use App\Models\Hospital;
use App\Models\User;
use App\Services\BloodSupplyForecastService;
use App\Services\BloodRequestService;
use App\Services\DonorFilterService;
use App\Services\DonorNotificationTimingService;
use App\Services\EmergencyBroadcastModeService;
use App\Services\EmergencyDashboardService;
use App\Services\HospitalInviteCodeService;
use App\Services\InventoryMonitoringService;
use App\Services\NationalSystemsIntegrationService;
use App\Services\NotificationService;
use App\Services\SystemSettingsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AdminPanelController extends Controller
{
    public function dashboard(
        Request $request,
        BloodSupplyForecastService $forecastService,
        InventoryMonitoringService $inventoryService,
        EmergencyBroadcastModeService $emergencyBroadcastModeService,
        DonorNotificationTimingService $timingService,
        NotificationService $notificationService
    ): JsonResponse
    {
        $forecastMonths = max(3, min(12, (int) $request->integer('forecast_months', 6)));

        $totalRequests = BloodRequest::query()->count();
        $completedRequests = BloodRequest::query()->whereIn('status', ['completed', 'fulfilled'])->count();
        $successRate = $totalRequests > 0 ? round(($completedRequests / $totalRequests) * 100, 2) : 0.0;

        $responseTimeBaseQuery = DonorRequestResponse::query()
            ->join('blood_requests', 'blood_requests.id', '=', 'donor_request_responses.blood_request_id');

        $driver = DB::connection()->getDriverName();

        $avgResponseRaw = $driver === 'sqlite'
            ? $responseTimeBaseQuery
                ->selectRaw('AVG((julianday(donor_request_responses.responded_at) - julianday(blood_requests.created_at)) * 24 * 60) as avg_minutes')
                ->value('avg_minutes')
            : $responseTimeBaseQuery
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, blood_requests.created_at, donor_request_responses.responded_at)) as avg_minutes')
                ->value('avg_minutes');

        $avgResponseMinutes = round(
            $avgResponseRaw ?? 0,
            2
        );

        $forecasting = $forecastService->buildForecast($forecastMonths);

        return response()->json([
            'metrics' => [
                'total_donors' => Donor::query()->count(),
                'active_donors' => Donor::query()->where('availability', true)->count(),
                'requests_today' => BloodRequest::query()->whereDate('created_at', today())->count(),
                'success_rate' => $successRate,
                'response_time_minutes' => $avgResponseMinutes,
            ],
            'forecasting' => $forecasting,
            'low_inventory_alerts' => $inventoryService->lowStockAlerts(),
            'pending_hospitals' => Hospital::query()->where('status', 'pending')->latest()->get(),
            'recent_requests' => BloodRequest::query()->latest()->take(20)->get(),
            'active_donor_list' => Donor::query()->where('availability', true)->latest()->take(20)->get(),
            'emergency_mode' => $emergencyBroadcastModeService->state(),
            'disaster_response_mode' => $emergencyBroadcastModeService->disasterResponseState(),
            'smart_notification_timing' => $timingService->dashboardInsights(),
            'notification_health' => $notificationService->notificationHealth(),
        ]);
    }

    public function emergencyModeStatus(EmergencyBroadcastModeService $emergencyBroadcastModeService): JsonResponse
    {
        $emergencyMode = $emergencyBroadcastModeService->state();
        $disasterResponseMode = $emergencyBroadcastModeService->disasterResponseState();

        return response()->json([
            'data' => [
                // Backward-compatible fields for existing clients.
                'enabled' => (bool) ($emergencyMode['enabled'] ?? false),
                'trigger' => $emergencyMode['trigger'] ?? null,
                'activated_at' => $emergencyMode['activated_at'] ?? null,
                'activated_by' => $emergencyMode['activated_by'] ?? null,
                'emergency_mode' => $emergencyMode,
                'disaster_response_mode' => $disasterResponseMode,
            ],
        ]);
    }

    public function settings(SystemSettingsService $systemSettingsService): JsonResponse
    {
        return response()->json([
            'data' => $systemSettingsService->current(),
        ]);
    }

    public function updateSettings(Request $request, SystemSettingsService $systemSettingsService): JsonResponse
    {
        $validated = $request->validate([
            'urgency_threshold' => ['required', 'integer', 'min:1', 'max:100'],
            'notification_rule' => ['required', 'string', 'in:critical-only,balanced,broadcast-all,emergency-active'],
            'weights.priority' => ['required', 'numeric', 'min:0', 'max:1'],
            'weights.availability' => ['required', 'numeric', 'min:0', 'max:1'],
            'weights.distance' => ['required', 'numeric', 'min:0', 'max:1'],
            'weights.time' => ['required', 'numeric', 'min:0', 'max:1'],
            'weight_profiles' => ['sometimes', 'array'],
            'control_center' => ['sometimes', 'array'],
            'weight_profiles.low.priority' => ['required_with:weight_profiles', 'numeric', 'min:0', 'max:1'],
            'weight_profiles.low.availability' => ['required_with:weight_profiles', 'numeric', 'min:0', 'max:1'],
            'weight_profiles.low.distance' => ['required_with:weight_profiles', 'numeric', 'min:0', 'max:1'],
            'weight_profiles.low.time' => ['required_with:weight_profiles', 'numeric', 'min:0', 'max:1'],
            'weight_profiles.medium.priority' => ['required_with:weight_profiles', 'numeric', 'min:0', 'max:1'],
            'weight_profiles.medium.availability' => ['required_with:weight_profiles', 'numeric', 'min:0', 'max:1'],
            'weight_profiles.medium.distance' => ['required_with:weight_profiles', 'numeric', 'min:0', 'max:1'],
            'weight_profiles.medium.time' => ['required_with:weight_profiles', 'numeric', 'min:0', 'max:1'],
            'weight_profiles.high.priority' => ['required_with:weight_profiles', 'numeric', 'min:0', 'max:1'],
            'weight_profiles.high.availability' => ['required_with:weight_profiles', 'numeric', 'min:0', 'max:1'],
            'weight_profiles.high.distance' => ['required_with:weight_profiles', 'numeric', 'min:0', 'max:1'],
            'weight_profiles.high.time' => ['required_with:weight_profiles', 'numeric', 'min:0', 'max:1'],
            'weight_profiles.critical.priority' => ['required_with:weight_profiles', 'numeric', 'min:0', 'max:1'],
            'weight_profiles.critical.availability' => ['required_with:weight_profiles', 'numeric', 'min:0', 'max:1'],
            'weight_profiles.critical.distance' => ['required_with:weight_profiles', 'numeric', 'min:0', 'max:1'],
            'weight_profiles.critical.time' => ['required_with:weight_profiles', 'numeric', 'min:0', 'max:1'],
        ]);

        $settings = $systemSettingsService->update([
            'urgency_threshold' => $validated['urgency_threshold'],
            'notification_rule' => $validated['notification_rule'],
            'weights' => $validated['weights'],
            'weight_profiles' => $validated['weight_profiles'] ?? null,
            'control_center' => $validated['control_center'] ?? null,
        ], $request->user()?->id);

        return response()->json([
            'message' => 'System settings saved successfully.',
            'data' => $settings,
        ]);
    }

    public function setEmergencyMode(Request $request, EmergencyBroadcastModeService $emergencyBroadcastModeService): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'trigger' => ['nullable', 'string', 'max:255'],
            'expires_in_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
        ]);

        if ((bool) $validated['enabled'] && blank($validated['trigger'] ?? null)) {
            return response()->json([
                'message' => 'A trigger is required when activating emergency mode.',
            ], 422);
        }

        $state = (bool) $validated['enabled']
            ? $emergencyBroadcastModeService->activate(
                $validated['trigger'] ?? null,
                $request->user()?->id,
                isset($validated['expires_in_hours']) ? (int) $validated['expires_in_hours'] : null
            )
            : $emergencyBroadcastModeService->deactivate($request->user()?->id);

        return response()->json([
            'message' => (bool) $validated['enabled']
                ? 'Emergency Broadcast Mode activated.'
                : 'Emergency Broadcast Mode deactivated.',
            'data' => $state,
        ]);
    }

    public function emergencyDashboardLive(Request $request, EmergencyDashboardService $emergencyDashboardService): JsonResponse
    {
        $limit = max(1, min(50, (int) $request->integer('limit', 10)));

        return response()->json([
            'data' => $emergencyDashboardService->snapshot($limit),
        ]);
    }

    public function nationalIntegrationPartners(NationalSystemsIntegrationService $integrationService): JsonResponse
    {
        return response()->json([
            'data' => $integrationService->partners(),
        ]);
    }

    public function syncNationalIntegrationEmergency(
        Request $request,
        string $partner,
        NationalSystemsIntegrationService $integrationService
    ): JsonResponse {
        $limit = max(1, min(50, (int) $request->integer('limit', 20)));

        try {
            $result = $integrationService->syncEmergencyDashboard($partner, $request->user()?->id, $limit);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 404);
        }

        $statusCode = match ($result['status'] ?? 'failed') {
            'success' => 200,
            'skipped' => 202,
            default => 502,
        };

        return response()->json([
            'message' => $result['message'] ?? 'Integration sync completed.',
            'data' => $result,
        ], $statusCode);
    }

    public function nationalIntegrationLogs(Request $request): JsonResponse
    {
        $perPage = min(100, max(5, (int) $request->integer('per_page', 20)));

        return response()->json(
            NationalPartnerSyncLog::query()
                ->latest('synced_at')
                ->paginate($perPage)
        );
    }

    public function approveHospital(Request $request, Hospital $hospital): JsonResponse
    {
        $hospital->update(['status' => 'approved']);

        ActivityLog::record($request->user()?->id, 'hospital.approved', [
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
        ]);

        return response()->json([
            'message' => 'Hospital approved.',
            'data' => $hospital,
        ]);
    }

    public function rejectHospital(Request $request, Hospital $hospital): JsonResponse
    {
        $hospital->update(['status' => 'rejected']);

        ActivityLog::record($request->user()?->id, 'hospital.rejected', [
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
        ]);

        return response()->json([
            'message' => 'Hospital rejected.',
            'data' => $hospital,
        ]);
    }

    public function bloodRequests(Request $request): JsonResponse
    {
        $perPage = min(100, max(5, (int) $request->integer('per_page', 20)));

        $query = BloodRequest::query()->latest();

        if ($request->filled('urgency_level')) {
            $query->where('urgency_level', $request->input('urgency_level'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('location')) {
            $loc = $request->input('location');
            $query->where(function ($q) use ($loc) {
                $q->where('city', 'like', "%{$loc}%")
                  ->orWhere('province', 'like', "%{$loc}%");
            });
        }
        if ($request->has('is_emergency')) {
            $query->where('is_emergency', $request->boolean('is_emergency'));
        }

        return response()->json([
            'success' => true,
            'data'    => $query->paginate($perPage),
            'message' => 'Blood requests retrieved successfully.',
        ]);
    }

    public function triggerMatching(Request $request, BloodRequest $bloodRequest): JsonResponse
    {
        ProcessBloodRequestMatchingJob::dispatch(
            bloodRequestId:  $bloodRequest->id,
            actorUserId:     $request->user()?->id,
            distanceLimitKm: (int) ($bloodRequest->distance_limit_km ?? 50),
        )->onQueue('matching');

        $bloodRequest->update(['status' => 'matching']);

        ActivityLog::record($request->user()?->id, 'request.matching_triggered', [
            'blood_request_id' => $bloodRequest->id,
            'triggered_by'     => 'admin',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Matching job triggered successfully.',
            'data'    => $bloodRequest->fresh(),
        ]);
    }

    public function activeDonors(Request $request): JsonResponse
    {
        $perPage = min(100, max(5, (int) $request->integer('per_page', 20)));

        return response()->json(
            Donor::query()->where('availability', true)->latest()->paginate($perPage)
        );
    }

    public function users(Request $request): JsonResponse
    {
        $perPage = min(100, max(5, (int) $request->integer('per_page', 20)));

        return response()->json(
            User::query()->latest()->paginate($perPage)
        );
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'role' => ['sometimes', 'required', 'in:admin,donor,hospital'],
        ]);

        $nextRole = $validated['role'] ?? $user->role;
        $nextEmail = $validated['email'] ?? $user->email;

        if ($nextRole === 'admin' && ! $this->isApprovedPrcAdminDomain($nextEmail)) {
            return response()->json([
                'message' => 'Admin role can only be assigned to approved Philippine Red Cross email domains.',
            ], 422);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'User updated.',
            'data' => $user,
        ]);
    }

    public function deleteUser(User $user): JsonResponse
    {
        $user->delete();

        return response()->json([
            'message' => 'User deleted.',
        ]);
    }

    private function isApprovedPrcAdminDomain(string $email): bool
    {
        $allowedDomains = collect(explode(',', (string) env('PRC_ADMIN_EMAIL_DOMAINS', 'redcross.org.ph,prc.org.ph')))
            ->map(fn ($domain) => Str::lower(trim($domain)))
            ->filter()
            ->values()
            ->all();

        $emailDomain = Str::lower(Str::after((string) $email, '@'));

        return in_array($emailDomain, $allowedDomains, true);
    }

    public function hospitals(Request $request): JsonResponse
    {
        $perPage = min(100, max(5, (int) $request->integer('per_page', 20)));
        $page = max(1, (int) $request->integer('page', 1));

        $hospitals = Hospital::query()
            ->with(['user:id,name,email'])
            ->withCount([
                'bloodRequests as total_requests_count',
                'bloodRequests as active_requests_count' => fn ($query) => $query->whereIn('status', ['pending', 'matching']),
                'bloodRequests as critical_requests_count' => fn ($query) => $query
                    ->whereIn('status', ['pending', 'matching'])
                    ->where(function ($nested) {
                        $nested->where('urgency_level', 'critical')
                            ->orWhere('is_emergency', true);
                    }),
            ])
            ->latest()
            ->get()
            ->map(fn (Hospital $hospital) => $this->buildHospitalIntelligence($hospital));

        $filtered = $hospitals
            ->when($request->filled('search'), fn ($collection) => $collection->filter(function (array $hospital) use ($request) {
                $needle = Str::lower(trim((string) $request->input('search')));

                return Str::contains(Str::lower((string) $hospital['name']), $needle);
            }))
            ->when($request->filled('status'), fn ($collection) => $collection->where('operational_status', Str::lower((string) $request->input('status'))))
            ->when($request->filled('location'), fn ($collection) => $collection->filter(function (array $hospital) use ($request) {
                $needle = Str::lower(trim((string) $request->input('location')));

                return Str::contains(Str::lower((string) $hospital['location']), $needle);
            }))
            ->when($request->filled('blood_demand'), fn ($collection) => $collection->filter(function (array $hospital) use ($request) {
                $needle = Str::upper(trim((string) $request->input('blood_demand')));

                return in_array($needle, $hospital['blood_types_needed'] ?? [], true);
            }))
            ->when($request->filled('request_urgency'), fn ($collection) => $collection->filter(function (array $hospital) use ($request) {
                $desired = Str::lower(trim((string) $request->input('request_urgency')));

                return in_array($desired, $hospital['active_request_urgencies'] ?? [], true);
            }))
            ->values();

        $total = $filtered->count();
        $items = $filtered->forPage($page, $perPage)->values();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
        $to = $total === 0 ? 0 : min($total, $page * $perPage);

        return response()->json([
            'success' => true,
            'message' => 'Hospital intelligence feed retrieved successfully.',
            'data' => [
                'data' => $items,
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $from,
                'to' => $to,
                'summary' => [
                    'total_hospitals' => $total,
                    'active_hospitals' => $filtered->where('operational_status', 'active')->count(),
                    'critical_requests' => $filtered->sum('critical_requests_count'),
                    'avg_response_time' => round((float) ($filtered->avg('avg_response_time') ?? 0), 2),
                ],
                'context' => [
                    'refresh_interval_seconds' => 45,
                    'last_updated' => now()->toISOString(),
                ],
            ],
        ]);
    }

    public function hospitalProfile(Hospital $hospital): JsonResponse
    {
        $hospital->loadMissing(['user:id,name,email']);

        $activityMetrics = $this->hospitalActivityMetrics($hospital);
        $activeRequests = $this->hospitalRequestsCollection($hospital)
            ->filter(fn (BloodRequest $request) => in_array($request->status, ['pending', 'matching'], true))
            ->values()
            ->map(fn (BloodRequest $request) => $this->serializeHospitalRequest($request))
            ->all();

        return response()->json([
            'success' => true,
            'message' => 'Hospital profile retrieved successfully.',
            'data' => [
                'id' => $hospital->id,
                'name' => $hospital->hospital_name,
                'address' => $hospital->address ?? $hospital->location,
                'location' => $hospital->location ?? $hospital->address,
                'contact_person' => $hospital->contact_person,
                'phone' => $hospital->contact_number,
                'email' => $hospital->email ?? $hospital->user?->email,
                'operational_status' => $this->hospitalOperationalStatus($hospital),
                'disabled' => $this->hospitalDisabled($hospital),
                'activity_metrics' => $activityMetrics,
                'real_time_data' => [
                    'current_active_requests' => $activeRequests,
                    'blood_types_needed' => collect($activeRequests)->pluck('blood_type')->filter()->unique()->values()->all(),
                    'last_activity' => $this->hospitalLastActivity($hospital),
                ],
                'system_intelligence' => [
                    'reliability_score' => $this->hospitalReliabilityScore($hospital, $activityMetrics),
                    'flags' => $this->hospitalRiskFlags($hospital, $activityMetrics),
                ],
            ],
        ]);
    }

    public function hospitalRequests(Hospital $hospital): JsonResponse
    {
        $requests = $this->hospitalRequestsCollection($hospital)
            ->map(fn (BloodRequest $request) => $this->serializeHospitalRequest($request))
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Hospital requests retrieved successfully.',
            'data' => [
                'pending_requests' => $requests->where('status', 'pending')->values(),
                'completed_requests' => $requests->filter(fn (array $request) => in_array($request['status'], ['completed', 'fulfilled'], true))->values(),
                'failed_requests' => $requests->where('status', 'cancelled')->values(),
                'all_requests' => $requests,
            ],
        ]);
    }

    public function alertHospital(Request $request, Hospital $hospital, NotificationService $notificationService): JsonResponse
    {
        if ($hospital->user) {
            $notificationService->sendPushNotification(
                user: $hospital->user,
                type: 'admin_hospital_alert',
                title: 'Emergency Coordination Alert',
                message: 'The operations center flagged your facility for immediate coordination review.',
                data: [
                    'type' => 'admin_hospital_alert',
                    'hospital_id' => $hospital->id,
                ],
            );
        }

        ActivityLog::record($request->user()?->id, 'hospital.alerted', [
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Hospital alert dispatched successfully.',
        ]);
    }

    public function toggleHospitalStatus(Request $request, Hospital $hospital): JsonResponse
    {
        $disabled = ! $this->hospitalDisabled($hospital);
        Cache::put($this->hospitalStatusCacheKey($hospital), ['disabled' => $disabled], now()->addDay());

        ActivityLog::record($request->user()?->id, 'hospital.toggle_status', [
            'hospital_id' => $hospital->id,
            'disabled' => $disabled,
        ]);

        return response()->json([
            'success' => true,
            'message' => $disabled ? 'Hospital disabled.' : 'Hospital enabled.',
            'data' => [
                'disabled' => $disabled,
            ],
        ]);
    }

    public function donors(Request $request, DonorFilterService $donorFilterService): JsonResponse
    {
        $perPage = min(100, max(5, (int) $request->integer('per_page', 20)));
        $page = max(1, (int) $request->integer('page', 1));
        $activeRequest = $this->resolveActiveRequestContext();

        $donors = Donor::query()
            ->with(['user:id,name,email'])
            ->withCount([
                'requestMatches as total_requests_received',
                'requestResponses as accepted_requests_count' => fn ($query) => $query->where('response', 'accepted'),
                'requestResponses as declined_requests_count' => fn ($query) => $query->where('response', 'declined'),
                'requestResponses as total_responses_count',
            ])
            ->latest()
            ->get()
            ->map(fn (Donor $donor) => $this->buildDonorIntelligence($donor, $activeRequest, $donorFilterService));

        $filtered = $donors
            ->when($request->filled('blood_type'), fn ($collection) => $collection->where('blood_type', $request->input('blood_type')))
            ->when($request->filled('eligibility_status'), fn ($collection) => $collection->filter(function (array $donor) use ($request) {
                $desired = Str::lower((string) $request->input('eligibility_status'));

                return $desired === 'eligible'
                    ? $donor['eligibility_status']['is_eligible']
                    : ! $donor['eligibility_status']['is_eligible'];
            }))
            ->when($request->filled('reliability_score'), fn ($collection) => $collection->where('reliability_band', Str::lower((string) $request->input('reliability_score'))))
            ->when($request->filled('availability_status'), fn ($collection) => $collection->where('availability_status', Str::lower((string) $request->input('availability_status'))))
            ->when($request->filled('location'), fn ($collection) => $collection->filter(function (array $donor) use ($request) {
                $location = Str::lower(trim((string) $request->input('location')));

                return Str::contains(Str::lower((string) $donor['city']), $location);
            }))
            ->when($request->filled('radius_km'), fn ($collection) => $collection->filter(function (array $donor) use ($request) {
                $radiusKm = (float) $request->input('radius_km');

                return isset($donor['distance']) && $donor['distance'] !== null && $donor['distance'] <= $radiusKm;
            }))
            ->when($request->filled('search'), fn ($collection) => $collection->filter(function (array $donor) use ($request) {
                $needle = Str::lower(trim((string) $request->input('search')));
                $haystacks = [
                    $donor['name'] ?? '',
                    $donor['contact_info']['phone'] ?? '',
                ];

                foreach ($haystacks as $haystack) {
                    if (Str::contains(Str::lower((string) $haystack), $needle)) {
                        return true;
                    }
                }

                return false;
            }))
            ->values();

        $total = $filtered->count();
        $items = $filtered->forPage($page, $perPage)->values();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
        $to = $total === 0 ? 0 : min($total, $page * $perPage);

        return response()->json([
            'success' => true,
            'message' => 'Donor intelligence feed retrieved successfully.',
            'data' => [
                'data' => $items,
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $from,
                'to' => $to,
                'summary' => [
                    'total_donors' => $total,
                    'eligible_donors' => $filtered->where('eligibility_status.is_eligible', true)->count(),
                    'high_reliability_donors' => $filtered->where('reliability_band', 'high')->count(),
                    'inactive_donors' => $filtered->where('availability_status', 'unavailable')->count(),
                ],
                'context' => [
                    'active_request_id' => $activeRequest?->id,
                    'active_request_blood_type' => $activeRequest?->blood_type,
                    'active_request_city' => $activeRequest?->city,
                    'match_distance_limit_km' => (float) ($activeRequest?->distance_limit_km ?? DonorFilterService::DEFAULT_DISTANCE_LIMIT_KM),
                    'refresh_interval_seconds' => 45,
                ],
            ],
        ]);
    }

    public function donorProfile(Donor $donor, DonorFilterService $donorFilterService): JsonResponse
    {
        $donor->loadMissing(['user:id,name,email', 'requestResponses', 'requestMatches', 'donationHistories']);
        $activeRequest = $this->resolveActiveRequestContext();
        $intelligence = $this->buildDonorIntelligence($donor, $activeRequest, $donorFilterService);

        return response()->json([
            'success' => true,
            'message' => 'Donor profile retrieved successfully.',
            'data' => [
                'id' => $donor->id,
                'full_name' => $donor->name,
                'blood_type' => $donor->blood_type,
                'contact_info' => [
                    'phone' => $donor->phone ?? $donor->contact_number,
                    'email' => $donor->email ?? $donor->user?->email,
                ],
                'address' => [
                    'city' => $donor->city,
                    'coordinates' => $donor->latitude !== null && $donor->longitude !== null
                        ? [
                            'latitude' => (float) $donor->latitude,
                            'longitude' => (float) $donor->longitude,
                        ]
                        : null,
                ],
                'last_donation_date' => optional($donor->last_donation_date)?->toDateString(),
                'eligibility_status' => $intelligence['eligibility_status'],
                'availability_status' => $intelligence['availability_status'],
                'distance' => $intelligence['distance'],
                'match_ready' => $intelligence['match_ready'],
                'prioritized' => $intelligence['prioritized'],
                'performance_metrics' => $this->buildDonorPerformanceMetrics($donor),
                'system_intelligence' => [
                    'reliability_score' => $intelligence['reliability_score'],
                    'reliability_band' => $intelligence['reliability_band'],
                    'risk_flags' => $this->riskFlagsForDonor($donor),
                    'active_request_context' => $activeRequest ? [
                        'id' => $activeRequest->id,
                        'blood_type' => $activeRequest->blood_type,
                        'city' => $activeRequest->city,
                    ] : null,
                ],
            ],
        ]);
    }

    public function notifyDonor(Request $request, Donor $donor, NotificationService $notificationService, DonorFilterService $donorFilterService): JsonResponse
    {
        $activeRequest = $this->resolveBestRequestForDonor($donor, $donorFilterService);

        if ($activeRequest) {
            $notificationService->sendDonorAlert(
                donor: $donor,
                bloodRequest: $activeRequest,
                distanceKm: $this->distanceToRequest($donor, $activeRequest, $donorFilterService),
            );
        } elseif ($donor->user) {
            $notificationService->sendPushNotification(
                user: $donor->user,
                type: 'admin_donor_readiness_ping',
                title: 'Donor Readiness Check',
                message: 'A blood operations coordinator requested a readiness update. Please confirm your availability.',
                data: [
                    'type' => 'admin_donor_readiness_ping',
                    'donor_id' => $donor->id,
                ],
            );
        }

        ActivityLog::record($request->user()?->id, 'donor.notified', [
            'donor_id' => $donor->id,
            'blood_request_id' => $activeRequest?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => $activeRequest
                ? 'Donor notification dispatched for the highest-priority active request.'
                : 'Donor readiness notification dispatched.',
        ]);
    }

    public function suspendDonor(Request $request, Donor $donor): JsonResponse
    {
        $donor->update(['availability' => false]);

        ActivityLog::record($request->user()?->id, 'donor.suspended', [
            'donor_id' => $donor->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Donor suspended successfully.',
            'data' => [
                'availability' => false,
            ],
        ]);
    }

    public function prioritizeDonor(Request $request, Donor $donor): JsonResponse
    {
        Cache::put($this->prioritizedDonorCacheKey($donor), [
            'prioritized_by' => $request->user()?->id,
            'prioritized_at' => now()->toISOString(),
        ], now()->addDay());

        ActivityLog::record($request->user()?->id, 'donor.prioritized', [
            'donor_id' => $donor->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Donor prioritized for emergency matching.',
            'data' => [
                'prioritized' => true,
            ],
        ]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'range' => ['nullable', 'string', 'in:24h,7d,30d,90d,custom'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'severity' => ['nullable', 'string', 'in:critical,high,medium,low,info'],
            'status' => ['nullable', 'string', 'in:success,failed,warning,blocked'],
            'category' => ['nullable', 'string', 'in:authentication,access,blood_requests,matching,notifications,admin,system,data_access,operations'],
            'actor_role' => ['nullable', 'string', 'in:admin,donor,hospital'],
            'action' => ['nullable', 'string', 'max:120'],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'query' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $filters = $this->resolveAuditLogFilters($validated);
        $perPage = min(100, max(5, (int) ($validated['per_page'] ?? 20)));
        $page = max(1, (int) ($validated['page'] ?? 1));

        $baseLogs = ActivityLog::query()
            ->with('actor:id,name,email,role')
            ->when($filters['start_at'], fn ($query, Carbon $startAt) => $query->where('created_at', '>=', $startAt))
            ->when($filters['end_at'], fn ($query, Carbon $endAt) => $query->where('created_at', '<=', $endAt))
            ->when($filters['action'], fn ($query, string $action) => $query->where('action', $action))
            ->latest('created_at')
            ->limit(600)
            ->get();

        $entries = $baseLogs
            ->map(fn (ActivityLog $log) => $this->transformAuditLogEntry($log))
            ->filter(fn (array $entry) => $this->matchesAuditLogFilters($entry, $filters))
            ->values();

        $paginator = new LengthAwarePaginator(
            $entries->forPage($page, $perPage)->values()->all(),
            $entries->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return response()->json([
            'data' => [
                'system_status' => $this->buildAuditSystemStatus($entries),
                'summary' => $this->buildAuditSummary($entries),
                'filters' => [
                    'applied' => [
                        'range' => $filters['range'],
                        'start_date' => $filters['start_at']->toDateString(),
                        'end_date' => $filters['end_at']->toDateString(),
                        'severity' => $filters['severity'],
                        'status' => $filters['status'],
                        'category' => $filters['category'],
                        'actor_role' => $filters['actor_role'],
                        'action' => $filters['action'],
                        'ip_address' => $filters['ip_address'],
                        'query' => $filters['query'],
                    ],
                    'options' => $this->buildAuditFilterOptions($baseLogs, $entries),
                ],
                'high_priority_alerts' => $this->buildAuditAlerts($entries),
                'table_view' => [
                    'data' => $paginator->items(),
                    'pagination' => [
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                    ],
                ],
                'timeline_view' => $this->buildAuditTimeline($entries),
                'export' => [
                    'available_formats' => ['json', 'csv'],
                    'file_name' => 'audit-log-report-'.now()->format('Ymd-His'),
                    'generated_at' => now()->toISOString(),
                ],
                'live_updates' => [
                    'enabled' => true,
                    'poll_interval_seconds' => 20,
                    'last_updated' => now()->toISOString(),
                ],
            ],
        ]);
    }

    private function resolveAuditLogFilters(array $validated): array
    {
        $range = $validated['range'] ?? '24h';

        if (($validated['range'] ?? null) === 'custom' && ! empty($validated['start_date']) && ! empty($validated['end_date'])) {
            $startAt = Carbon::parse($validated['start_date'])->startOfDay();
            $endAt = Carbon::parse($validated['end_date'])->endOfDay();
        } else {
            $endAt = now();
            $startAt = match ($range) {
                '7d' => now()->subDays(7),
                '30d' => now()->subDays(30),
                '90d' => now()->subDays(90),
                default => now()->subDay(),
            };
        }

        return [
            'range' => $range,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'severity' => $validated['severity'] ?? null,
            'status' => $validated['status'] ?? null,
            'category' => $validated['category'] ?? null,
            'actor_role' => $validated['actor_role'] ?? null,
            'action' => $validated['action'] ?? null,
            'ip_address' => $validated['ip_address'] ?? null,
            'query' => $validated['query'] ?? null,
        ];
    }

    private function transformAuditLogEntry(ActivityLog $log): array
    {
        $details = ActivityLog::normalizeDetails($log->action, $log->details ?? []);
        $actor = $log->actor;
        $actorRole = $actor?->role ?? ($details['actor_role'] ?? null) ?? 'system';
        $targetLabel = $details['target_label'] ?? $details['hospital_name'] ?? $details['case_id'] ?? $details['email'] ?? null;

        $entry = [
            'id' => $log->id,
            'action' => $log->action,
            'title' => $this->auditLogTitle($log->action),
            'description' => $this->auditLogDescription($log->action, $details),
            'severity' => (string) ($details['severity'] ?? 'info'),
            'status' => (string) ($details['status'] ?? 'success'),
            'category' => (string) ($details['category'] ?? 'operations'),
            'timestamp' => $log->created_at?->toISOString(),
            'actor' => [
                'id' => $actor?->id,
                'name' => $actor?->name ?? ($details['actor_name'] ?? 'System'),
                'email' => $actor?->email,
                'role' => $actorRole,
            ],
            'ip_address' => $details['ip'] ?? null,
            'method' => $details['method'] ?? null,
            'path' => $details['path'] ?? null,
            'http_status' => $details['http_status'] ?? null,
            'target' => [
                'type' => $details['target_type'] ?? null,
                'id' => $details['target_id'] ?? null,
                'label' => $targetLabel,
            ],
            'details' => $details,
        ];

        $entry['search_text'] = Str::lower(implode(' | ', array_filter([
            $entry['action'],
            $entry['title'],
            $entry['description'],
            $entry['actor']['name'],
            $entry['actor']['email'],
            $entry['actor']['role'],
            $entry['ip_address'],
            $entry['path'],
            $entry['target']['label'],
        ])));

        return $entry;
    }

    private function matchesAuditLogFilters(array $entry, array $filters): bool
    {
        if ($filters['severity'] && $entry['severity'] !== $filters['severity']) {
            return false;
        }

        if ($filters['status'] && $entry['status'] !== $filters['status']) {
            return false;
        }

        if ($filters['category'] && $entry['category'] !== $filters['category']) {
            return false;
        }

        if ($filters['actor_role'] && $entry['actor']['role'] !== $filters['actor_role']) {
            return false;
        }

        if ($filters['ip_address'] && ! Str::contains((string) ($entry['ip_address'] ?? ''), $filters['ip_address'])) {
            return false;
        }

        if ($filters['query'] && ! Str::contains($entry['search_text'], Str::lower($filters['query']))) {
            return false;
        }

        return true;
    }

    private function buildAuditSystemStatus(Collection $entries): array
    {
        $criticalEvents = $entries->whereIn('severity', ['critical', 'high'])->count();
        $failedEvents = $entries->whereIn('status', ['failed', 'blocked'])->count();
        $unauthorizedAttempts = $entries->where('action', 'security.unauthorized-role-access')->count();

        [$label, $tone, $message] = match (true) {
            $criticalEvents >= 5 || $unauthorizedAttempts >= 2 => ['Investigating', 'critical', 'Security-critical activity requires immediate review.'],
            $criticalEvents > 0 || $failedEvents >= 3 => ['Monitored', 'warning', 'Audit stream shows elevated risk signals and repeated failures.'],
            default => ['Stable', 'stable', 'No major compliance or security anomalies detected in the selected window.'],
        };

        return [
            'label' => $label,
            'tone' => $tone,
            'message' => $message,
            'last_event_at' => $entries->first()['timestamp'] ?? null,
            'open_alerts_count' => min(3, $criticalEvents + $unauthorizedAttempts),
        ];
    }

    private function buildAuditSummary(Collection $entries): array
    {
        $adminOverrideActions = [
            'donor.suspended',
            'donor.prioritized',
            'hospital.toggle_status',
            'hospital.approved',
            'hospital.rejected',
            'request.updated',
        ];

        return [
            'total_events' => $entries->count(),
            'critical_events' => $entries->whereIn('severity', ['critical', 'high'])->count(),
            'failed_actions' => $entries->whereIn('status', ['failed', 'blocked'])->count(),
            'unauthorized_attempts' => $entries->where('action', 'security.unauthorized-role-access')->count(),
            'admin_overrides' => $entries->whereIn('action', $adminOverrideActions)->count(),
            'severity_breakdown' => collect(['critical', 'high', 'medium', 'low', 'info'])
                ->map(fn (string $severity) => [
                    'severity' => $severity,
                    'count' => $entries->where('severity', $severity)->count(),
                ])
                ->values(),
            'category_breakdown' => collect([
                'authentication',
                'access',
                'blood_requests',
                'matching',
                'notifications',
                'admin',
                'system',
                'data_access',
                'operations',
            ])->map(fn (string $category) => [
                'category' => $category,
                'count' => $entries->where('category', $category)->count(),
            ])->values(),
        ];
    }

    private function buildAuditAlerts(Collection $entries): array
    {
        $alerts = collect();

        $unauthorizedAttempts = $entries->where('action', 'security.unauthorized-role-access')->count();
        if ($unauthorizedAttempts > 0) {
            $alerts->push([
                'id' => 'unauthorized-access',
                'tone' => 'critical',
                'title' => 'Unauthorized access attempts blocked',
                'detail' => $unauthorizedAttempts.' role-based access attempts were denied in the selected audit window.',
            ]);
        }

        $failedLogins = $entries->where('action', 'auth.login.failed')->count();
        if ($failedLogins > 0) {
            $alerts->push([
                'id' => 'failed-logins',
                'tone' => 'warning',
                'title' => 'Repeated authentication failures detected',
                'detail' => $failedLogins.' failed login attempts were captured and should be reviewed for abuse or training issues.',
            ]);
        }

        $adminOverrides = $entries->where('category', 'admin')->count();
        if ($adminOverrides > 0) {
            $alerts->push([
                'id' => 'admin-overrides',
                'tone' => 'info',
                'title' => 'Administrative overrides present',
                'detail' => $adminOverrides.' high-privilege interventions were recorded and remain traceable for compliance review.',
            ]);
        }

        return $alerts->take(3)->values()->all();
    }

    private function buildAuditFilterOptions(Collection $baseLogs, Collection $entries): array
    {
        return [
            'ranges' => [
                ['value' => '24h', 'label' => 'Last 24 hours'],
                ['value' => '7d', 'label' => 'Last 7 days'],
                ['value' => '30d', 'label' => 'Last 30 days'],
                ['value' => '90d', 'label' => 'Last 90 days'],
                ['value' => 'custom', 'label' => 'Custom range'],
            ],
            'severities' => ['critical', 'high', 'medium', 'low', 'info'],
            'statuses' => ['success', 'failed', 'warning', 'blocked'],
            'categories' => ['authentication', 'access', 'blood_requests', 'matching', 'notifications', 'admin', 'system', 'data_access', 'operations'],
            'actor_roles' => $entries->pluck('actor.role')->filter()->unique()->values(),
            'actions' => $baseLogs->pluck('action')->filter()->unique()->take(25)->values(),
            'ip_addresses' => $entries->pluck('ip_address')->filter()->unique()->take(20)->values(),
        ];
    }

    private function buildAuditTimeline(Collection $entries): array
    {
        return $entries
            ->take(15)
            ->map(fn (array $entry) => [
                'id' => $entry['id'],
                'title' => $entry['title'],
                'description' => $entry['description'],
                'timestamp' => $entry['timestamp'],
                'severity' => $entry['severity'],
                'status' => $entry['status'],
                'category' => $entry['category'],
                'actor_name' => $entry['actor']['name'],
                'target_label' => $entry['target']['label'],
            ])
            ->values()
            ->all();
    }

    private function auditLogTitle(string $action): string
    {
        return match ($action) {
            'auth.login.failed' => 'Failed Login Attempt',
            'auth.login.blocked' => 'Blocked Login Attempt',
            'auth.login.succeeded' => 'Successful Sign-in',
            'auth.logout.succeeded' => 'User Signed Out',
            'auth.registration.succeeded' => 'New Account Registered',
            'security.unauthorized-role-access' => 'Unauthorized Access Blocked',
            'data.access' => 'Protected Data Accessed',
            default => (string) Str::of($action)->replace(['.', '-', '_'], ' ')->title(),
        };
    }

    private function auditLogDescription(string $action, array $details): string
    {
        return match ($action) {
            'auth.login.failed' => 'Login failed for '.($details['target_label'] ?? $details['email'] ?? 'an account').' from '.($details['ip'] ?? 'an unknown IP').'.',
            'auth.login.blocked' => 'Login blocked because the account is pending approval or restricted.',
            'auth.login.succeeded' => 'Authenticated session issued for '.($details['target_label'] ?? 'the user').'.',
            'auth.logout.succeeded' => 'Authenticated session closed cleanly.',
            'security.unauthorized-role-access' => 'Blocked access to '.($details['path'] ?? 'a protected route').' due to role mismatch.',
            'data.access' => 'Protected endpoint '.($details['path'] ?? 'unknown').' returned HTTP '.($details['http_status'] ?? '200').'.',
            default => implode(' ', array_filter([
                $details['target_label'] ?? null,
                isset($details['path']) ? 'Route '.$details['path'].'.' : null,
                isset($details['reason']) ? 'Reason: '.Str::of((string) $details['reason'])->replace('_', ' ')->lower().'.' : null,
            ])) ?: 'Operational activity recorded for compliance and monitoring.',
        };
    }

    public function analytics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'range' => ['nullable', 'string', 'in:daily,weekly,monthly'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'blood_type' => ['nullable', 'string', 'max:5'],
            'hospital_id' => ['nullable', 'integer', 'exists:hospitals,id'],
            'urgency_level' => ['nullable', 'string', 'in:low,medium,high,critical'],
        ]);

        $filters = $this->resolveAnalyticsFilters($validated);

        $currentRequests = $this->analyticsRequestQuery($filters, $filters['current_start'], $filters['current_end'])
            ->with([
                'hospital:id,hospital_name',
                'donorResponses:id,donor_id,blood_request_id,response,responded_at,created_at',
                'matches:id,blood_request_id,donor_id,score,response_status,rank,created_at',
            ])
            ->orderByDesc('created_at')
            ->get();

        $previousRequests = $this->analyticsRequestQuery($filters, $filters['previous_start'], $filters['previous_end'])
            ->with([
                'donorResponses:id,donor_id,blood_request_id,response,responded_at,created_at',
                'matches:id,blood_request_id,donor_id,score,response_status,rank,created_at',
            ])
            ->get();

        $currentMetrics = $this->buildAnalyticsMetrics($currentRequests);
        $previousMetrics = $this->buildAnalyticsMetrics($previousRequests);
        $kpis = $this->buildAnalyticsKpis($currentMetrics, $previousMetrics);
        $systemHealth = $this->buildAnalyticsSystemHealth($currentMetrics);
        $trendPayload = [
            'matching_speed_trend' => $this->buildMatchingSpeedTrend($currentRequests, $filters),
            'success_rate_by_urgency' => $this->buildSuccessRateByUrgency($currentRequests),
            'average_score_distribution' => $this->buildAverageScoreDistribution($currentRequests),
        ];

        $bottlenecks = $this->buildAnalyticsBottlenecks($currentRequests, $filters);
        $geographicIntelligence = $this->buildAnalyticsGeographicIntelligence($currentRequests, $filters);
        $predictiveInsights = $this->buildPredictiveInsights($currentRequests, $currentMetrics, $filters, $geographicIntelligence);
        $liveActivityFeed = $this->buildAnalyticsActivityFeed($currentRequests);
        $executiveSummary = $this->buildAnalyticsExecutiveSummary(
            $currentRequests,
            $currentMetrics,
            $kpis,
            $systemHealth,
            $predictiveInsights,
            $geographicIntelligence
        );

        return response()->json([
            'data' => [
                'filters' => [
                    'applied' => [
                        'range' => $filters['range'],
                        'start_date' => $filters['current_start']->toDateString(),
                        'end_date' => $filters['current_end']->toDateString(),
                        'blood_type' => $filters['blood_type'],
                        'hospital_id' => $filters['hospital_id'],
                        'urgency_level' => $filters['urgency_level'],
                    ],
                    'options' => [
                        'blood_types' => BloodRequest::query()
                            ->select('blood_type')
                            ->whereNotNull('blood_type')
                            ->distinct()
                            ->orderBy('blood_type')
                            ->pluck('blood_type')
                            ->values(),
                        'hospitals' => Hospital::query()
                            ->select('id', 'hospital_name')
                            ->orderBy('hospital_name')
                            ->get()
                            ->map(fn (Hospital $hospital) => [
                                'id' => $hospital->id,
                                'name' => $hospital->hospital_name,
                            ])
                            ->values(),
                        'urgency_levels' => ['low', 'medium', 'high', 'critical'],
                    ],
                ],
                'executive_summary' => $executiveSummary,
                'kpis' => $kpis,
                'system_health' => $systemHealth,
                'live_activity_feed' => $liveActivityFeed,
                'algorithm_transparency' => $trendPayload,
                'insights' => [
                    'predictive' => $predictiveInsights,
                    'bottlenecks' => $bottlenecks,
                    'geographic_intelligence' => $geographicIntelligence,
                ],
                'meta' => [
                    'generated_at' => now()->toISOString(),
                    'range_label' => $filters['range_label'],
                    'request_count' => $currentRequests->count(),
                ],
            ],
        ]);
    }

    private function resolveAnalyticsFilters(array $validated): array
    {
        $range = $validated['range'] ?? 'weekly';

        if (! empty($validated['start_date']) && ! empty($validated['end_date'])) {
            $currentStart = Carbon::parse($validated['start_date'])->startOfDay();
            $currentEnd = Carbon::parse($validated['end_date'])->endOfDay();
        } else {
            $currentEnd = now()->endOfDay();
            $currentStart = match ($range) {
                'daily' => now()->subDays(6)->startOfDay(),
                'monthly' => now()->subDays(179)->startOfDay(),
                default => now()->subDays(41)->startOfDay(),
            };
        }

        $periodDays = max(1, $currentStart->diffInDays($currentEnd) + 1);
        $previousEnd = $currentStart->copy()->subSecond();
        $previousStart = $previousEnd->copy()->subDays($periodDays - 1)->startOfDay();

        return [
            'range' => $range,
            'range_label' => $currentStart->toFormattedDateString() . ' - ' . $currentEnd->toFormattedDateString(),
            'current_start' => $currentStart,
            'current_end' => $currentEnd,
            'previous_start' => $previousStart,
            'previous_end' => $previousEnd,
            'blood_type' => $validated['blood_type'] ?? null,
            'hospital_id' => isset($validated['hospital_id']) ? (int) $validated['hospital_id'] : null,
            'urgency_level' => $validated['urgency_level'] ?? null,
        ];
    }

    private function analyticsRequestQuery(array $filters, Carbon $start, Carbon $end)
    {
        return BloodRequest::query()
            ->whereBetween('created_at', [$start, $end])
            ->when($filters['blood_type'], fn ($query, $bloodType) => $query->where('blood_type', $bloodType))
            ->when($filters['hospital_id'], fn ($query, $hospitalId) => $query->where('hospital_id', $hospitalId))
            ->when($filters['urgency_level'], fn ($query, $urgencyLevel) => $query->where('urgency_level', $urgencyLevel));
    }

    private function buildAnalyticsMetrics($requests): array
    {
        $matchTimes = $requests->map(fn (BloodRequest $request) => $this->analyticsMatchTimeSeconds($request))
            ->filter(fn ($value) => $value !== null)
            ->values();

        $responseTimes = $requests->map(fn (BloodRequest $request) => $this->analyticsResponseTimeSeconds($request))
            ->filter(fn ($value) => $value !== null)
            ->values();

        $scoreValues = $requests->flatMap(fn (BloodRequest $request) => $request->matches->pluck('score'))
            ->filter(fn ($value) => $value !== null)
            ->values();

        $totalNotifications = (int) $requests->sum(fn (BloodRequest $request) => max(
            (int) ($request->notifications_sent ?? 0),
            $request->donorResponses->count(),
            (int) ($request->matched_donors_count ?? 0)
        ));

        $totalResponses = (int) $requests->sum(fn (BloodRequest $request) => max(
            (int) ($request->responses_received ?? 0),
            $request->donorResponses->count()
        ));

        $successfulRequests = (int) $requests->filter(function (BloodRequest $request) {
            return in_array($request->status, ['completed', 'fulfilled'], true)
                || (int) ($request->accepted_donors ?? 0) > 0;
        })->count();

        $noMatchRequests = (int) $requests->filter(function (BloodRequest $request) {
            return (int) ($request->matched_donors_count ?? 0) === 0
                && $request->matches->isEmpty()
                && ! in_array($request->status, ['completed', 'fulfilled'], true);
        })->count();

        $dropOffCount = max(0, $totalNotifications - $totalResponses);

        return [
            'request_count' => $requests->count(),
            'average_match_time_seconds' => round((float) ($matchTimes->avg() ?? 0), 2),
            'average_response_time_seconds' => round((float) ($responseTimes->avg() ?? 0), 2),
            'donor_response_rate' => $totalNotifications > 0 ? round(($totalResponses / $totalNotifications) * 100, 2) : 0.0,
            'successful_matches_rate' => $requests->count() > 0 ? round(($successfulRequests / $requests->count()) * 100, 2) : 0.0,
            'drop_off_rate' => $totalNotifications > 0 ? round(($dropOffCount / $totalNotifications) * 100, 2) : 0.0,
            'total_notifications' => $totalNotifications,
            'total_responses' => $totalResponses,
            'successful_requests' => $successfulRequests,
            'drop_off_count' => $dropOffCount,
            'no_match_requests' => $noMatchRequests,
            'average_score' => round((float) ($scoreValues->avg() ?? 0), 2),
        ];
    }

    private function buildAnalyticsKpis(array $currentMetrics, array $previousMetrics): array
    {
        return [
            'average_match_time_seconds' => $this->analyticsKpiPayload(
                value: $currentMetrics['average_match_time_seconds'],
                previousValue: $previousMetrics['average_match_time_seconds'],
                lowerIsBetter: true,
                suffix: 's'
            ),
            'donor_response_rate' => $this->analyticsKpiPayload(
                value: $currentMetrics['donor_response_rate'],
                previousValue: $previousMetrics['donor_response_rate'],
                suffix: '%'
            ),
            'successful_matches_rate' => $this->analyticsKpiPayload(
                value: $currentMetrics['successful_matches_rate'],
                previousValue: $previousMetrics['successful_matches_rate'],
                suffix: '%'
            ),
            'drop_off_rate' => $this->analyticsKpiPayload(
                value: $currentMetrics['drop_off_rate'],
                previousValue: $previousMetrics['drop_off_rate'],
                lowerIsBetter: true,
                suffix: '%'
            ),
        ];
    }

    private function analyticsKpiPayload(float $value, float $previousValue, bool $lowerIsBetter = false, string $suffix = ''): array
    {
        if ($previousValue <= 0.0) {
            $trendPercentage = 0.0;
        } else {
            $rawDelta = (($value - $previousValue) / $previousValue) * 100;
            $trendPercentage = round($lowerIsBetter ? ($rawDelta * -1) : $rawDelta, 2);
        }

        $tone = $trendPercentage > 2
            ? 'positive'
            : ($trendPercentage < -2 ? 'negative' : 'neutral');

        return [
            'value' => round($value, 2),
            'display_value' => round($value, 2) . $suffix,
            'trend_percentage' => $trendPercentage,
            'trend_label' => $trendPercentage > 0
                ? '+' . $trendPercentage . '% vs previous period'
                : $trendPercentage . '% vs previous period',
            'tone' => $tone,
        ];
    }

    private function buildAnalyticsSystemHealth(array $metrics): array
    {
        $status = 'healthy';
        $label = 'Healthy';
        $message = 'Matching speed and donor responsiveness are within target thresholds.';

        if ($metrics['donor_response_rate'] < 30 || $metrics['no_match_requests'] >= max(2, (int) ceil($metrics['request_count'] * 0.35))) {
            $status = 'critical';
            $label = 'Critical';
            $message = 'Critical: no donors are responding fast enough for a meaningful share of active requests.';
        } elseif ($metrics['donor_response_rate'] < 55 || $metrics['average_match_time_seconds'] > 120) {
            $status = 'slowing';
            $label = 'Slowing';
            $message = 'Performance is slowing. Escalation pressure and response delays are increasing.';
        }

        return [
            'status' => $status,
            'label' => $label,
            'message' => $message,
        ];
    }

    private function buildMatchingSpeedTrend($requests, array $filters): array
    {
        $grouped = $requests->groupBy(function (BloodRequest $request) use ($filters) {
            return $filters['range'] === 'monthly'
                ? $request->created_at->format('Y-m')
                : $request->created_at->format('Y-m-d');
        })->sortKeys();

        return $grouped->map(function ($bucket, $label) use ($filters) {
            $value = collect($bucket)->map(fn (BloodRequest $request) => $this->analyticsMatchTimeSeconds($request))
                ->filter(fn ($seconds) => $seconds !== null)
                ->avg() ?? 0;

            return [
                'label' => $filters['range'] === 'monthly'
                    ? Carbon::parse($label . '-01')->format('M Y')
                    : Carbon::parse($label)->format('M d'),
                'value' => round((float) $value, 2),
            ];
        })->values()->all();
    }

    private function buildSuccessRateByUrgency($requests): array
    {
        return collect(['low', 'medium', 'high', 'critical'])->map(function (string $urgency) use ($requests) {
            $bucket = $requests->where('urgency_level', $urgency);
            $successful = $bucket->filter(fn (BloodRequest $request) => in_array($request->status, ['completed', 'fulfilled'], true) || (int) ($request->accepted_donors ?? 0) > 0)->count();

            return [
                'label' => Str::title($urgency),
                'value' => $bucket->count() > 0 ? round(($successful / $bucket->count()) * 100, 2) : 0.0,
            ];
        })->values()->all();
    }

    private function buildAverageScoreDistribution($requests): array
    {
        $scores = $requests->flatMap(fn (BloodRequest $request) => $request->matches->pluck('score'))
            ->filter(fn ($score) => $score !== null)
            ->map(fn ($score) => (float) $score);

        $buckets = [
            '0-20' => 0,
            '21-40' => 0,
            '41-60' => 0,
            '61-80' => 0,
            '81-100' => 0,
        ];

        foreach ($scores as $score) {
            if ($score <= 20) {
                $buckets['0-20']++;
            } elseif ($score <= 40) {
                $buckets['21-40']++;
            } elseif ($score <= 60) {
                $buckets['41-60']++;
            } elseif ($score <= 80) {
                $buckets['61-80']++;
            } else {
                $buckets['81-100']++;
            }
        }

        return collect($buckets)->map(fn ($value, $label) => [
            'label' => $label,
            'value' => $value,
        ])->values()->all();
    }

    private function buildAnalyticsActivityFeed($requests): array
    {
        $requestIds = $requests->pluck('id')->all();
        $activityLogs = ActivityLog::query()
            ->latest('created_at')
            ->limit(120)
            ->get()
            ->filter(function (ActivityLog $log) use ($requestIds) {
                $details = $log->details ?? [];
                $requestId = (int) ($details['blood_request_id'] ?? $details['request_id'] ?? 0);

                return in_array($requestId, $requestIds, true);
            });

        $entries = collect();

        foreach ($requests as $request) {
            $matchTime = $this->analyticsMatchTimeSeconds($request);

            if ($matchTime !== null) {
                $entries->push([
                    'id' => 'match-' . $request->id,
                    'title' => sprintf('Request #%d matched in %ss', $request->id, (int) round($matchTime)),
                    'detail' => sprintf('%s at %s', $request->blood_type, $request->city ?? 'Unknown region'),
                    'timestamp' => optional($request->matches->sortBy('created_at')->first())->created_at?->toISOString()
                        ?? optional($request->donorResponses->sortBy('responded_at')->first())->responded_at?->toISOString(),
                    'tone' => 'success',
                ]);
            }

            if ((int) ($request->responses_received ?? 0) === 0 && $request->created_at->diffInMinutes(now()) >= 60) {
                $entries->push([
                    'id' => 'escalation-' . $request->id,
                    'title' => 'Escalation triggered (no response in 60s)',
                    'detail' => sprintf('Request #%d · %s · %s', $request->id, $request->blood_type, $request->city ?? 'Unknown region'),
                    'timestamp' => $request->created_at->copy()->addMinute()->toISOString(),
                    'tone' => 'warning',
                ]);
            }
        }

        foreach ($activityLogs as $log) {
            $entries->push([
                'id' => 'activity-' . $log->id,
                'title' => Str::headline(str_replace('.', ' ', $log->action)),
                'detail' => 'Audit event recorded for an active request.',
                'timestamp' => $log->created_at?->toISOString(),
                'tone' => str_contains($log->action, 'cancel') || str_contains($log->action, 'reject') ? 'critical' : 'neutral',
            ]);
        }

        return $entries
            ->filter(fn (array $entry) => ! empty($entry['timestamp']))
            ->sortByDesc('timestamp')
            ->take(10)
            ->values()
            ->all();
    }

    private function buildPredictiveInsights($requests, array $metrics, array $filters, array $geographicIntelligence): array
    {
        $insights = [];

        $hourBuckets = [];

        foreach ($requests as $request) {
            foreach ($request->donorResponses as $response) {
                if (! $response->responded_at) {
                    continue;
                }

                $hour = (int) $response->responded_at->format('G');

                foreach ([0, 1, 2] as $offset) {
                    $bucketHour = ($hour + $offset) % 24;
                    $hourBuckets[$bucketHour] = ($hourBuckets[$bucketHour] ?? 0) + 1;
                }
            }
        }

        if (! empty($hourBuckets)) {
            $peakHour = (int) collect($hourBuckets)->sortDesc()->keys()->first();
            $start = Carbon::createFromTime($peakHour);
            $end = $start->copy()->addHours(3);
            $insights[] = sprintf('Peak donor activity: %s-%s.', $start->format('gA'), $end->format('gA'));
        }

        $bloodTypeRates = $requests
            ->groupBy('blood_type')
            ->map(function ($bucket) {
                $notifications = (int) collect($bucket)->sum(fn (BloodRequest $request) => max((int) ($request->notifications_sent ?? 0), $request->donorResponses->count()));
                $responses = (int) collect($bucket)->sum(fn (BloodRequest $request) => max((int) ($request->responses_received ?? 0), $request->donorResponses->count()));

                return [
                    'blood_type' => $bucket->first()->blood_type,
                    'response_rate' => $notifications > 0 ? ($responses / $notifications) * 100 : 0,
                ];
            })
            ->sortBy('response_rate')
            ->values();

        if ($bloodTypeRates->isNotEmpty()) {
            $lowest = $bloodTypeRates->first();
            $insights[] = sprintf('Low response rate for %s blood type at %.1f%%.', $lowest['blood_type'], $lowest['response_rate']);
        }

        $weekendTimes = [];
        $weekdayTimes = [];

        foreach ($requests as $request) {
            $time = $this->analyticsResponseTimeSeconds($request);
            if ($time === null) {
                continue;
            }

            if ($request->created_at->isWeekend()) {
                $weekendTimes[] = $time;
            } else {
                $weekdayTimes[] = $time;
            }
        }

        if (! empty($weekendTimes) && ! empty($weekdayTimes)) {
            $weekendAverage = collect($weekendTimes)->avg();
            $weekdayAverage = collect($weekdayTimes)->avg();

            if ($weekdayAverage > 0) {
                $faster = round((($weekdayAverage - $weekendAverage) / $weekdayAverage) * 100, 1);
                $insights[] = $faster >= 0
                    ? sprintf('Weekends show %.1f%% faster response.', $faster)
                    : sprintf('Weekends are %.1f%% slower than weekdays.', abs($faster));
            }
        }

        if (empty($insights)) {
            $insights[] = $metrics['request_count'] > 0
                ? 'Operational data is still stabilizing; collect one more full cycle for stronger predictions.'
                : 'No request activity in the selected window yet.';
        }

        return $insights;
    }

    private function buildAnalyticsBottlenecks($requests, array $filters): array
    {
        $requestsWithNoMatches = $requests
            ->filter(function (BloodRequest $request) {
                return (int) ($request->matched_donors_count ?? 0) === 0
                    && $request->matches->isEmpty()
                    && ! in_array($request->status, ['completed', 'fulfilled'], true);
            })
            ->take(6)
            ->map(fn (BloodRequest $request) => [
                'request_id' => $request->id,
                'case_id' => $request->case_id,
                'blood_type' => $request->blood_type,
                'hospital_name' => $request->hospital_name,
                'region' => $request->province ?: $request->city,
                'elapsed_minutes' => $request->created_at->diffInMinutes(now()),
            ])
            ->values();

        $slowResponseClusters = $requests
            ->groupBy(fn (BloodRequest $request) => $request->province ?: $request->city ?: 'Unknown')
            ->map(function ($bucket, $region) {
                $responseTimes = collect($bucket)->map(fn (BloodRequest $request) => $this->analyticsResponseTimeSeconds($request))
                    ->filter(fn ($value) => $value !== null);

                if ($responseTimes->isEmpty()) {
                    return null;
                }

                return [
                    'region' => $region,
                    'average_response_time_seconds' => round((float) $responseTimes->avg(), 2),
                    'request_count' => $bucket->count(),
                ];
            })
            ->filter()
            ->sortByDesc('average_response_time_seconds')
            ->take(5)
            ->values();

        return [
            'requests_with_no_matches' => $requestsWithNoMatches,
            'slow_response_clusters' => $slowResponseClusters,
            'regions_with_low_donor_density' => collect($geographicIntelligence = $this->buildAnalyticsGeographicIntelligence($requests, $filters)['underserved_areas'])
                ->take(5)
                ->values(),
        ];
    }

    private function buildAnalyticsGeographicIntelligence($requests, array $filters): array
    {
        $donorQuery = Donor::query();

        if (! empty($filters['blood_type'])) {
            $donorQuery->where('blood_type', $filters['blood_type']);
        }

        $donors = $donorQuery->get();

        $requestRegions = $requests
            ->groupBy(fn (BloodRequest $request) => $request->province ?: $request->city ?: 'Unknown')
            ->map(fn ($bucket) => $bucket->count());

        $donorRegions = $donors
            ->groupBy(fn (Donor $donor) => $donor->city ?: 'Unknown')
            ->map(fn ($bucket) => $bucket->count());

        $regions = $requestRegions->keys()->merge($donorRegions->keys())->unique()->values();

        $distribution = $regions->map(function (string $region) use ($requestRegions, $donorRegions) {
            $requestCount = (int) ($requestRegions[$region] ?? 0);
            $donorCount = (int) ($donorRegions[$region] ?? 0);
            $densityScore = $requestCount > 0 ? round(min(100, ($donorCount / max(1, $requestCount)) * 25), 2) : ($donorCount > 0 ? 100.0 : 0.0);

            return [
                'region' => $region,
                'donor_count' => $donorCount,
                'request_count' => $requestCount,
                'density_score' => $densityScore,
                'underserved' => $requestCount > 0 && $donorCount <= $requestCount,
            ];
        })->sortBy([['underserved', 'desc'], ['density_score', 'asc']])->values();

        return [
            'donor_distribution' => $distribution->take(8)->values(),
            'underserved_areas' => $distribution->where('underserved', true)->take(6)->values(),
        ];
    }

    private function buildAnalyticsExecutiveSummary(
        $requests,
        array $metrics,
        array $kpis,
        array $systemHealth,
        array $predictiveInsights,
        array $geographicIntelligence
    ): array {
        $efficiencyScore = round(max(0, min(100,
            ($metrics['successful_matches_rate'] * 0.42)
            + ($metrics['donor_response_rate'] * 0.28)
            + ((100 - min(100, $metrics['drop_off_rate'])) * 0.18)
            + ((100 - min(100, $metrics['average_match_time_seconds'] / 3)) * 0.12)
        )), 1);

        $improvement = $kpis['average_match_time_seconds']['trend_percentage'];
        $criticalGap = collect($geographicIntelligence['underserved_areas'])->first();
        $criticalRequest = $requests->first(function (BloodRequest $request) {
            return in_array($request->urgency_level, ['high', 'critical'], true)
                && (int) ($request->matched_donors_count ?? 0) === 0;
        });

        $shortageStatement = $criticalRequest
            ? sprintf('Critical shortage detected for %s in %s.', $criticalRequest->blood_type, $criticalRequest->province ?: $criticalRequest->city ?: 'the selected region')
            : ($criticalGap
                ? sprintf('Coverage risk detected in %s with donor density at %.1f%%.', $criticalGap['region'], $criticalGap['density_score'])
                : 'No critical regional shortages detected in the selected period.');

        return [
            'headline' => sprintf('System is operating at %.1f%% efficiency.', $efficiencyScore),
            'summary_lines' => [
                sprintf('Average match time %s by %.1f%% this period.', $improvement >= 0 ? 'improved' : 'declined', abs($improvement)),
                $shortageStatement,
                $predictiveInsights[0] ?? $systemHealth['message'],
            ],
            'efficiency_score' => $efficiencyScore,
        ];
    }

    private function analyticsMatchTimeSeconds(BloodRequest $request): ?float
    {
        $candidateTimes = collect([
            optional($request->matches->sortBy('created_at')->first())->created_at,
            optional($request->donorResponses->where('response', 'accepted')->sortBy('responded_at')->first())->responded_at,
        ])->filter();

        $firstMatch = $candidateTimes->sortBy(fn ($timestamp) => $timestamp->getTimestamp())->first();

        if (! $firstMatch || ! $request->created_at) {
            return null;
        }

        return (float) $request->created_at->diffInSeconds($firstMatch);
    }

    private function analyticsResponseTimeSeconds(BloodRequest $request): ?float
    {
        $firstResponse = $request->donorResponses
            ->sortBy('responded_at')
            ->first()?->responded_at;

        if (! $firstResponse || ! $request->created_at) {
            return null;
        }

        return (float) $request->created_at->diffInSeconds($firstResponse);
    }

    public function requestMatchedDonors(BloodRequest $bloodRequest): JsonResponse
    {
        $rankedDonors = $bloodRequest->matches()
            ->with('donor')
            ->orderBy('rank')
            ->get()
            ->map(fn (RequestMatch $match) => [
                'donor_id' => $match->donor_id,
                'donor_name' => $match->donor?->name ?? 'Unknown',
                'priority_score' => 0,
                'distance_score' => 0,
                'availability_score' => 0,
                'response_history_score' => 0,
                'total_compatibility_score' => (int) round($match->score),
            ]);

        return response()->json(['data' => ['ranked_donors' => $rankedDonors]]);
    }

    public function pastMatchRequestOptions(Request $request): JsonResponse
    {
        $search = trim((string) $request->string('search', ''));
        $limit = max(5, min(50, (int) $request->integer('limit', 20)));

        $requests = BloodRequest::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('case_id', 'like', "%{$search}%")
                        ->orWhere('hospital_name', 'like', "%{$search}%")
                        ->orWhere('blood_type', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (BloodRequest $bloodRequest) => $this->serializePastMatchRequestOption($bloodRequest))
            ->values();

        return response()->json([
            'data' => $requests,
        ]);
    }

    public function notificationRequestOptions(Request $request): JsonResponse
    {
        return $this->pastMatchRequestOptions($request);
    }

    public function notificationDashboard(BloodRequest $bloodRequest): JsonResponse
    {
        $bloodRequest->loadMissing(['hospital', 'donorResponses.donor.user']);

        $alerts = DonorAlertLog::query()
            ->where('blood_request_id', $bloodRequest->id)
            ->with('donor.user')
            ->orderByDesc('sent_at')
            ->get();

        $responses = DonorRequestResponse::query()
            ->where('blood_request_id', $bloodRequest->id)
            ->with('donor.user')
            ->orderByDesc('responded_at')
            ->get();

        $userIds = $alerts
            ->pluck('donor.user_id')
            ->merge($responses->pluck('donor.user_id'))
            ->filter()
            ->unique()
            ->values();

        $deliveries = NotificationDelivery::query()
            ->when($userIds->isNotEmpty(), fn ($query) => $query->whereIn('user_id', $userIds->all()))
            ->where('sent_at', '>=', $bloodRequest->created_at->copy()->subDay())
            ->latest('sent_at')
            ->get();

        $activityLogs = ActivityLog::query()
            ->latest()
            ->limit(250)
            ->get()
            ->filter(function (ActivityLog $log) use ($bloodRequest) {
                $details = $log->details ?? [];

                return (int) ($details['blood_request_id'] ?? $details['request_id'] ?? 0) === $bloodRequest->id;
            })
            ->values();

        $stream = $this->buildNotificationStream($bloodRequest, $alerts, $responses, $deliveries);
        $summary = $this->buildNotificationSummary($bloodRequest, $alerts, $responses, $stream);
        $insights = $this->buildNotificationInsights($alerts, $responses, $stream);
        $escalationTriggers = $this->buildNotificationEscalationTriggers($bloodRequest, $alerts, $activityLogs);
        $analytics = $this->buildNotificationAnalytics($bloodRequest, $alerts, $responses, $stream);
        $controls = $this->pastMatchControlState($bloodRequest);

        return response()->json([
            'data' => [
                'request' => $this->serializeNotificationRequestContext($bloodRequest, $alerts),
                'summary' => $summary,
                'notification_stream' => $stream,
                'engagement_insights' => $insights,
                'escalation_triggers' => $escalationTriggers,
                'controls' => $controls,
                'analytics' => $analytics,
                'meta' => [
                    'last_updated' => now()->toISOString(),
                    'auto_refresh_seconds' => 15,
                    'sync_status' => $controls['notifications_paused'] ? 'paused' : 'live-polling',
                ],
            ],
        ]);
    }

    public function notificationControl(
        Request $request,
        BloodRequest $bloodRequest,
        NotificationService $notificationService
    ): JsonResponse {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:resend_notification,manual_message,broadcast_eligible_donors,cancel_pending_notifications,resume_notifications'],
            'donor_id' => ['nullable', 'integer', 'exists:donors,id'],
            'message' => ['nullable', 'string', 'max:1000'],
            'title' => ['nullable', 'string', 'max:120'],
        ]);

        $message = 'Notification action completed.';

        switch ($validated['action']) {
            case 'resend_notification':
                $donor = Donor::query()->findOrFail((int) $validated['donor_id']);
                $notificationService->sendDonorAlert($donor, $bloodRequest);

                ActivityLog::record($request->user()?->id, 'notification.resend', [
                    'blood_request_id' => $bloodRequest->id,
                    'donor_id' => $donor->id,
                ]);

                $message = 'Notification resent to the selected donor.';
                break;

            case 'manual_message':
                $manualMessage = trim((string) ($validated['message'] ?? ''));

                if ($manualMessage === '') {
                    return response()->json(['message' => 'A manual message is required.'], 422);
                }

                $donor = Donor::query()->findOrFail((int) $validated['donor_id']);
                $notificationService->sendCustomDonorMessage(
                    donor: $donor,
                    bloodRequest: $bloodRequest,
                    message: $manualMessage,
                    title: (string) ($validated['title'] ?? 'Manual Admin Message'),
                );

                ActivityLog::record($request->user()?->id, 'notification.manual-message', [
                    'blood_request_id' => $bloodRequest->id,
                    'donor_id' => $donor->id,
                    'title' => $validated['title'] ?? 'Manual Admin Message',
                ]);

                $message = 'Manual message sent to the selected donor.';
                break;

            case 'broadcast_eligible_donors':
                SendEmergencyNotificationsJob::dispatch(
                    bloodRequestId: $bloodRequest->id,
                    escalationLevel: max(1, (int) (DonorAlertLog::query()->where('blood_request_id', $bloodRequest->id)->max('escalation_level') ?? 1)),
                )->onQueue('notifications');

                ActivityLog::record($request->user()?->id, 'notification.broadcast-all', [
                    'blood_request_id' => $bloodRequest->id,
                ]);

                $message = 'Broadcast queued for all eligible donors.';
                break;

            case 'cancel_pending_notifications':
                Cache::put($this->pastMatchControlCacheKey($bloodRequest), [
                    'notifications_paused' => true,
                    'updated_at' => now()->toISOString(),
                ], now()->addDay());

                ActivityLog::record($request->user()?->id, 'notification.pending-cancelled', [
                    'blood_request_id' => $bloodRequest->id,
                ]);

                $message = 'Pending notifications cancelled for this request.';
                break;

            case 'resume_notifications':
                Cache::put($this->pastMatchControlCacheKey($bloodRequest), [
                    'notifications_paused' => false,
                    'updated_at' => now()->toISOString(),
                ], now()->addDay());

                ActivityLog::record($request->user()?->id, 'notification.pending-resumed', [
                    'blood_request_id' => $bloodRequest->id,
                ]);

                $message = 'Notifications resumed for this request.';
                break;
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'request_id' => $bloodRequest->id,
                'controls' => $this->pastMatchControlState($bloodRequest),
            ],
        ]);
    }

    public function pastMatchDetails(
        BloodRequest $bloodRequest,
        DonorFilterService $donorFilterService,
        PASTMatch $pastMatch,
        NotificationService $notificationService
    ): JsonResponse {
        $bloodRequest->loadMissing(['hospital', 'matches.donor', 'donorResponses.donor']);

        $filteredDonors = $donorFilterService->filterForRequest(
            requestedBloodType: (string) $bloodRequest->blood_type,
            requestLatitude: $bloodRequest->latitude !== null ? (float) $bloodRequest->latitude : null,
            requestLongitude: $bloodRequest->longitude !== null ? (float) $bloodRequest->longitude : null,
            distanceLimitKm: (int) round((float) ($bloodRequest->distance_limit_km ?? DonorFilterService::DEFAULT_DISTANCE_LIMIT_KM)),
            requestCity: $bloodRequest->city,
            excludingRequestId: $bloodRequest->id,
        );

        $rankedCandidates = $pastMatch->rankDonors($filteredDonors, [
            'urgency_level' => $bloodRequest->urgency_level,
        ])->values();
        $matches = $bloodRequest->matches()->with('donor')->orderBy('rank')->get();
        $alerts = DonorAlertLog::query()
            ->where('blood_request_id', $bloodRequest->id)
            ->with('donor')
            ->orderBy('sent_at')
            ->get();
        $responses = DonorRequestResponse::query()
            ->where('blood_request_id', $bloodRequest->id)
            ->with('donor')
            ->orderBy('responded_at')
            ->get();
        $activityLogs = ActivityLog::query()
            ->latest()
            ->limit(250)
            ->get()
            ->filter(function (ActivityLog $log) use ($bloodRequest) {
                $details = $log->details ?? [];

                return (int) ($details['blood_request_id'] ?? $details['request_id'] ?? 0) === $bloodRequest->id;
            })
            ->values();

        $overview = [
            'total_donors_evaluated' => $filteredDonors->count(),
            'eligible_donors' => $filteredDonors->count(),
            'notified_donors' => $alerts->pluck('donor_id')->unique()->count(),
            'responded_donors' => $responses->count(),
        ];

        $rankedDonors = $this->buildPastMatchRankedDonors($bloodRequest, $rankedCandidates, $matches, $alerts, $responses)
            ->take(25)
            ->values();
        $timeline = $this->buildPastMatchTimeline($bloodRequest, $matches, $alerts, $responses, $activityLogs);
        $escalation = $this->buildPastMatchEscalation($bloodRequest, $rankedCandidates, $alerts, $responses);
        $matchingState = $this->buildPastMatchMatchingState($bloodRequest, $alerts, $responses, $escalation);
        $escalationTimeline = $this->buildPastMatchEscalationTimeline($bloodRequest, $escalation, app(EmergencyBroadcastModeService::class));
        $analytics = $this->buildPastMatchAnalytics($bloodRequest, $alerts, $responses, $escalation, $matchingState);
        $activityFeed = $this->buildPastMatchActivityFeed($bloodRequest, $alerts, $responses, $activityLogs)->take(40)->values();
        $controls = $this->pastMatchControlState($bloodRequest);

        $formula = $this->pastMatchFormulaPayload(app(SystemSettingsService::class), $bloodRequest->urgency_level);

        return response()->json([
            'data' => [
                'request' => $this->serializePastMatchRequestSummary($bloodRequest, $matchingState),
                'overview' => $overview,
                'matching_state' => $matchingState,
                'notification_health' => $notificationService->notificationHealth(),
                'ranked_donors' => $rankedDonors,
                'timeline' => $timeline,
                'escalation_timeline' => $escalationTimeline,
                'flow' => [
                    'current_stage' => $this->resolvePastMatchCurrentStage($timeline),
                    'delayed_stages' => collect($timeline)->where('delayed', true)->pluck('key')->values()->all(),
                ],
                'escalation' => $escalation,
                'controls' => $controls,
                'analytics' => $analytics,
                'activity_feed' => $activityFeed,
                'formula' => $formula,
                'meta' => [
                    'last_updated' => now()->toISOString(),
                    'auto_refresh_seconds' => 20,
                    'sync_status' => $controls['notifications_paused'] ? 'notifications-paused' : 'live-polling',
                ],
            ],
        ]);
    }

    public function updateRequest(Request $request, BloodRequest $bloodRequest, BloodRequestService $bloodRequestService): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'required', 'string', 'in:pending,matching,matched,confirmed,completed,fulfilled,cancelled'],
            'urgency_level' => ['sometimes', 'required', 'string', 'in:low,medium,high,critical'],
            'distance_limit_km' => ['sometimes', 'nullable', 'numeric', 'min:1', 'max:500'],
        ]);

        if (array_key_exists('status', $validated)) {
            $transitionError = $bloodRequestService->invalidTransitionReason($bloodRequest, $validated['status']);

            if ($transitionError !== null) {
                return response()->json([
                    'success' => false,
                    'message' => $transitionError,
                    'data' => null,
                ], 422);
            }
        }

        $bloodRequest->update($validated);

        ActivityLog::record($request->user()?->id, 'request.updated', [
            'blood_request_id' => $bloodRequest->id,
            'changes' => $validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Request updated.',
            'data' => $bloodRequest->fresh(),
        ]);
    }

    public function pastMatchControl(
        Request $request,
        BloodRequest $bloodRequest,
        EmergencyBroadcastModeService $emergencyBroadcastModeService
    ): JsonResponse {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:expand_radius,trigger_emergency_mode,rerun_matching,pause_notifications,resume_notifications'],
            'radius_km' => ['nullable', 'numeric', 'min:1', 'max:500'],
            'trigger' => ['nullable', 'string', 'max:255'],
            'expires_in_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
        ]);

        $message = 'Control action completed.';

        switch ($validated['action']) {
            case 'expand_radius':
                $radiusKm = (float) ($validated['radius_km'] ?? 0);

                if ($radiusKm <= 0) {
                    return response()->json(['message' => 'A valid radius_km value is required.'], 422);
                }

                $bloodRequest->update([
                    'distance_limit_km' => $radiusKm,
                    'status' => in_array($bloodRequest->status, ['completed', 'fulfilled', 'cancelled'], true) ? $bloodRequest->status : 'matching',
                ]);

                ProcessBloodRequestMatchingJob::dispatch(
                    bloodRequestId: $bloodRequest->id,
                    actorUserId: $request->user()?->id,
                    distanceLimitKm: (int) round($radiusKm),
                )->onQueue('matching');

                ActivityLog::record($request->user()?->id, 'past-match.radius-expanded', [
                    'blood_request_id' => $bloodRequest->id,
                    'radius_km' => $radiusKm,
                ]);

                $message = 'Matching radius expanded and rerun queued.';
                break;

            case 'trigger_emergency_mode':
                $state = $emergencyBroadcastModeService->activate(
                    $validated['trigger'] ?? 'PAST-Match monitoring override',
                    $request->user()?->id,
                    isset($validated['expires_in_hours']) ? (int) $validated['expires_in_hours'] : 2
                );

                SendEmergencyNotificationsJob::dispatch(
                    bloodRequestId: $bloodRequest->id,
                    escalationLevel: max(1, (int) (DonorAlertLog::query()->where('blood_request_id', $bloodRequest->id)->max('escalation_level') ?? 1)),
                )->onQueue('notifications');

                ActivityLog::record($request->user()?->id, 'past-match.emergency-mode-triggered', [
                    'blood_request_id' => $bloodRequest->id,
                    'emergency_mode' => $state,
                ]);

                $message = 'Emergency mode activated for the monitoring flow.';
                break;

            case 'rerun_matching':
                ProcessBloodRequestMatchingJob::dispatch(
                    bloodRequestId: $bloodRequest->id,
                    actorUserId: $request->user()?->id,
                    distanceLimitKm: (int) round((float) ($bloodRequest->distance_limit_km ?? DonorFilterService::DEFAULT_DISTANCE_LIMIT_KM)),
                )->onQueue('matching');

                $bloodRequest->update([
                    'status' => in_array($bloodRequest->status, ['completed', 'fulfilled', 'cancelled'], true) ? $bloodRequest->status : 'matching',
                ]);

                ActivityLog::record($request->user()?->id, 'past-match.matching-rerun', [
                    'blood_request_id' => $bloodRequest->id,
                    'radius_km' => (float) ($bloodRequest->distance_limit_km ?? DonorFilterService::DEFAULT_DISTANCE_LIMIT_KM),
                ]);

                $message = 'Matching rerun queued successfully.';
                break;

            case 'pause_notifications':
                Cache::put($this->pastMatchControlCacheKey($bloodRequest), [
                    'notifications_paused' => true,
                    'updated_at' => now()->toISOString(),
                ], now()->addDay());

                ActivityLog::record($request->user()?->id, 'past-match.notifications-paused', [
                    'blood_request_id' => $bloodRequest->id,
                ]);

                $message = 'Notifications paused for this request.';
                break;

            case 'resume_notifications':
                Cache::put($this->pastMatchControlCacheKey($bloodRequest), [
                    'notifications_paused' => false,
                    'updated_at' => now()->toISOString(),
                ], now()->addDay());

                ActivityLog::record($request->user()?->id, 'past-match.notifications-resumed', [
                    'blood_request_id' => $bloodRequest->id,
                ]);

                $message = 'Notifications resumed for this request.';
                break;
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'request_id' => $bloodRequest->id,
                'controls' => $this->pastMatchControlState($bloodRequest),
                'distance_limit_km' => round((float) ($bloodRequest->fresh()->distance_limit_km ?? DonorFilterService::DEFAULT_DISTANCE_LIMIT_KM), 2),
            ],
        ]);
    }

    public function updateDonorStatus(Request $request, Donor $donor): JsonResponse
    {
        $validated = $request->validate([
            'availability' => ['required', 'boolean'],
        ]);

        $donor->update(['availability' => $validated['availability']]);

        ActivityLog::record($request->user()?->id, 'donor.status_updated', [
            'donor_id' => $donor->id,
            'availability' => $validated['availability'],
        ]);

        return response()->json(['message' => 'Donor status updated.', 'data' => $donor]);
    }

    private function buildDonorIntelligence(Donor $donor, ?BloodRequest $activeRequest, DonorFilterService $donorFilterService): array
    {
        $eligibility = $this->eligibilityStatusForDonor($donor);
        $availabilityStatus = $this->availabilityStatusForDonor($donor);
        $reliabilityBand = $this->reliabilityBand((float) ($donor->reliability_score ?? 0));
        $distance = $activeRequest ? $this->distanceToRequest($donor, $activeRequest, $donorFilterService) : null;
        $matchDistanceLimit = (float) ($activeRequest?->distance_limit_km ?? DonorFilterService::DEFAULT_DISTANCE_LIMIT_KM);
        $withinDistance = $distance !== null && $distance <= $matchDistanceLimit;
        $prioritized = Cache::has($this->prioritizedDonorCacheKey($donor));

        return [
            'id' => $donor->id,
            'name' => $donor->name,
            'blood_type' => $donor->blood_type,
            'city' => $donor->city,
            'last_donation_date' => optional($donor->last_donation_date)?->toDateString(),
            'eligibility_status' => $eligibility,
            'reliability_score' => round((float) ($donor->reliability_score ?? 0), 2),
            'reliability_band' => $reliabilityBand,
            'last_reliability_label' => $donor->reliabilityLabel(),
            'availability_status' => $availabilityStatus,
            'distance' => $distance,
            'match_ready' => $eligibility['is_eligible'] && $reliabilityBand === 'high' && $availabilityStatus === 'available' && $withinDistance,
            'prioritized' => $prioritized,
            'risk_flags' => $this->riskFlagsForDonor($donor),
            'contact_info' => [
                'phone' => $donor->phone ?? $donor->contact_number,
                'email' => $donor->email ?? $donor->user?->email,
            ],
        ];
    }

    private function buildDonorPerformanceMetrics(Donor $donor): array
    {
        $totalRequestsReceived = (int) ($donor->total_requests_received ?? $donor->requestMatches()->count());
        $acceptedRequests = (int) ($donor->accepted_requests_count ?? $donor->requestResponses()->where('response', 'accepted')->count());
        $declinedRequests = (int) ($donor->declined_requests_count ?? $donor->requestResponses()->where('response', 'declined')->count());
        $totalResponses = (int) ($donor->total_responses_count ?? $donor->requestResponses()->count());
        $ignoredRequests = max(0, $totalRequestsReceived - $totalResponses);
        $averageResponseTimeMinutes = $this->averageResponseTimeMinutesForDonor($donor);

        return [
            'total_requests_received' => $totalRequestsReceived,
            'accepted_requests' => $acceptedRequests,
            'ignored_requests' => $ignoredRequests,
            'average_response_time_minutes' => $averageResponseTimeMinutes,
            'response_rate' => $totalRequestsReceived > 0
                ? round(($totalResponses / $totalRequestsReceived) * 100, 2)
                : 0.0,
            'declined_requests' => $declinedRequests,
        ];
    }

    private function eligibilityStatusForDonor(Donor $donor): array
    {
        $daysSinceLastDonation = $donor->daysSinceLastDonation();
        $daysRemaining = $daysSinceLastDonation === null
            ? 0
            : max(0, DonorFilterService::MIN_DONATION_INTERVAL_DAYS - $daysSinceLastDonation);

        return [
            'is_eligible' => $donor->isEligibleForDonation(),
            'label' => $donor->isEligibleForDonation()
                ? 'Eligible'
                : sprintf('Cooldown (%d days remaining)', $daysRemaining),
            'days_remaining' => $daysRemaining,
            'next_eligible_date' => optional($donor->nextEligibleDonationDate())?->toDateString(),
        ];
    }

    private function availabilityStatusForDonor(Donor $donor): string
    {
        if (! $donor->availability) {
            return 'unavailable';
        }

        $hasAcceptedActiveRequest = $donor->requestResponses()
            ->where('response', 'accepted')
            ->whereHas('bloodRequest', fn ($query) => $query->whereIn('status', ['pending', 'matching']))
            ->exists();

        return $hasAcceptedActiveRequest ? 'busy' : 'available';
    }

    private function reliabilityBand(float $score): string
    {
        return match (true) {
            $score > 80 => 'high',
            $score >= 50 => 'medium',
            default => 'low',
        };
    }

    private function riskFlagsForDonor(Donor $donor): array
    {
        $totalRequestsReceived = (int) ($donor->total_requests_received ?? $donor->requestMatches()->count());
        $acceptedRequests = (int) ($donor->accepted_requests_count ?? $donor->requestResponses()->where('response', 'accepted')->count());
        $declinedRequests = (int) ($donor->declined_requests_count ?? $donor->requestResponses()->where('response', 'declined')->count());
        $totalResponses = (int) ($donor->total_responses_count ?? $donor->requestResponses()->count());

        $flags = [];

        if ($totalRequestsReceived > 0 && ($totalResponses / $totalRequestsReceived) < 0.5) {
            $flags[] = 'Low response rate';
        }

        if ($totalResponses > 0 && $declinedRequests >= 3 && ($declinedRequests / max(1, $totalResponses)) >= 0.5) {
            $flags[] = 'Frequently declines';
        }

        if ($acceptedRequests === 0 && $totalRequestsReceived >= 5) {
            $flags[] = 'No accepted requests recorded';
        }

        return $flags;
    }

    private function averageResponseTimeMinutesForDonor(Donor $donor): float
    {
        $responses = $donor->requestResponses()
            ->whereNotNull('responded_at')
            ->get(['blood_request_id', 'responded_at']);

        if ($responses->isEmpty()) {
            return 0.0;
        }

        $matches = $donor->requestMatches()
            ->whereIn('blood_request_id', $responses->pluck('blood_request_id')->all())
            ->get(['blood_request_id', 'created_at'])
            ->keyBy('blood_request_id');

        $durations = $responses
            ->map(function (DonorRequestResponse $response) use ($matches) {
                $match = $matches->get($response->blood_request_id);

                if (! $match?->created_at || ! $response->responded_at) {
                    return null;
                }

                return $match->created_at->diffInMinutes($response->responded_at);
            })
            ->filter();

        return round((float) ($durations->avg() ?? 0.0), 2);
    }

    private function resolveActiveRequestContext(): ?BloodRequest
    {
        return BloodRequest::query()
            ->whereIn('status', ['pending', 'matching'])
            ->orderByDesc('is_emergency')
            ->orderByRaw("CASE urgency_level WHEN 'critical' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END DESC")
            ->latest()
            ->get()
            ->sortByDesc(fn (BloodRequest $request) => $request->latitude !== null && $request->longitude !== null)
            ->first();
    }

    private function resolveBestRequestForDonor(Donor $donor, DonorFilterService $donorFilterService): ?BloodRequest
    {
        return BloodRequest::query()
            ->whereIn('status', ['pending', 'matching'])
            ->get()
            ->filter(fn (BloodRequest $request) => in_array($donor->blood_type, $donorFilterService->compatibleDonorTypes($request->blood_type), true))
            ->sortByDesc(function (BloodRequest $request) {
                return [
                    $request->is_emergency ? 1 : 0,
                    match ($request->urgency_level) {
                        'critical' => 4,
                        'high' => 3,
                        'medium' => 2,
                        default => 1,
                    },
                    optional($request->created_at)?->timestamp ?? 0,
                ];
            })
            ->first();
    }

    private function distanceToRequest(Donor $donor, BloodRequest $request, DonorFilterService $donorFilterService): ?float
    {
        if ($donor->latitude !== null && $donor->longitude !== null && $request->latitude !== null && $request->longitude !== null) {
            return $donorFilterService->haversineDistanceKm(
                (float) $donor->latitude,
                (float) $donor->longitude,
                (float) $request->latitude,
                (float) $request->longitude,
            );
        }

        if ($request->city && Str::lower($request->city) === Str::lower((string) $donor->city)) {
            return 0.0;
        }

        return null;
    }

    private function prioritizedDonorCacheKey(Donor $donor): string
    {
        return 'admin:prioritized-donor:'.$donor->id;
    }

    private function serializeNotificationRequestContext(BloodRequest $bloodRequest, $alerts): array
    {
        $unitsRequired = (int) ($bloodRequest->units_required ?? $bloodRequest->requested_units ?? 0);
        $fulfilledUnits = (int) ($bloodRequest->fulfilled_units ?? 0);
        $progress = $unitsRequired > 0 ? round(($fulfilledUnits / $unitsRequired) * 100, 2) : 0.0;
        $status = $this->notificationRequestStatus($bloodRequest, $alerts);

        return [
            'id' => $bloodRequest->id,
            'request_id' => $bloodRequest->case_id ?: 'Request #'.$bloodRequest->id,
            'blood_type' => (string) $bloodRequest->blood_type,
            'component' => $bloodRequest->component ?: 'Whole Blood',
            'units_required' => $unitsRequired,
            'fulfilled_units' => $fulfilledUnits,
            'progress_percentage' => $progress,
            'urgency_level' => Str::lower((string) ($bloodRequest->urgency_level ?: 'medium')),
            'status' => $status,
            'time_elapsed_seconds' => (int) $bloodRequest->created_at->diffInSeconds(now()),
            'time_elapsed_human' => $this->notificationElapsedHuman($bloodRequest->created_at),
            'created_at' => $bloodRequest->created_at?->toISOString(),
            'hospital_name' => $bloodRequest->hospital_name,
        ];
    }

    private function buildNotificationStream(BloodRequest $bloodRequest, $alerts, $responses, $deliveries): array
    {
        $alertsByUserId = $alerts
            ->filter(fn (DonorAlertLog $alert) => $alert->donor?->user_id)
            ->groupBy(fn (DonorAlertLog $alert) => $alert->donor->user_id);

        $responsesByDonorId = $responses->keyBy('donor_id');
        $donorsByUserId = $alerts
            ->filter(fn (DonorAlertLog $alert) => $alert->donor?->user_id)
            ->mapWithKeys(fn (DonorAlertLog $alert) => [$alert->donor->user_id => $alert->donor])
            ->merge(
                $responses
                    ->filter(fn (DonorRequestResponse $response) => $response->donor?->user_id)
                    ->mapWithKeys(fn (DonorRequestResponse $response) => [$response->donor->user_id => $response->donor])
            );

        $matchedAlertIds = [];

        $deliveryEntries = $deliveries
            ->filter(fn (NotificationDelivery $delivery) => $this->notificationDeliveryBelongsToRequest($delivery, $bloodRequest, $alertsByUserId))
            ->map(function (NotificationDelivery $delivery) use ($alertsByUserId, $responsesByDonorId, $donorsByUserId, $bloodRequest, &$matchedAlertIds) {
                $deliveryTime = $delivery->sent_at ?? $delivery->created_at;
                $matchedAlert = $this->notificationDeliveryMatchedAlert($delivery, $alertsByUserId);

                if ($matchedAlert) {
                    $matchedAlertIds[] = $matchedAlert->id;
                }

                $donor = $donorsByUserId->get($delivery->user_id);
                $responseRecord = $donor ? $responsesByDonorId->get($donor->id) : null;
                $responseTimeSeconds = $this->notificationResponseTimeSeconds($deliveryTime, $responseRecord?->responded_at);
                $responseStatus = $responseRecord && $responseRecord->responded_at && $deliveryTime && $responseRecord->responded_at->greaterThanOrEqualTo($deliveryTime)
                    ? Str::lower((string) $responseRecord->response)
                    : 'pending';

                $attempt = (int) data_get($delivery->response, 'attempt', 1);
                $channel = Str::upper((string) $delivery->channel);

                return [
                    'id' => 'delivery-'.$delivery->id,
                    'donor_id' => $donor?->id,
                    'donor_name' => $donor?->name ?: ($delivery->user?->name ?: 'Unknown donor'),
                    'donor_code' => $donor ? 'DNR-'.$donor->id : null,
                    'channel' => $channel,
                    'message_preview' => Str::limit((string) (data_get($delivery->response, 'message') ?: $this->notificationDefaultMessagePreview($bloodRequest, $donor)), 120),
                    'notification_type' => $delivery->type,
                    'delivery_status' => $responseStatus !== 'pending'
                        ? 'Responded'
                        : ($delivery->status === 'failed' ? 'Failed' : 'Delivered'),
                    'failure_reason' => $this->notificationFailureReason($delivery),
                    'retry_attempts' => max(0, $attempt - 1),
                    'response_status' => Str::headline($responseStatus),
                    'response_reason' => data_get($responseRecord, 'reason'),
                    'timestamp' => $deliveryTime?->toISOString(),
                    'responded_at' => $responseRecord?->responded_at?->toISOString(),
                    'response_time_seconds' => $responseTimeSeconds,
                    'response_time_human' => $this->notificationResponseTimeHuman($responseTimeSeconds),
                    'speed_label' => $this->notificationSpeedLabel($responseTimeSeconds),
                ];
            })
            ->values();

        $placeholderEntries = $alerts
            ->filter(fn (DonorAlertLog $alert) => ! in_array($alert->id, $matchedAlertIds, true))
            ->map(function (DonorAlertLog $alert) use ($responsesByDonorId, $bloodRequest) {
                $responseRecord = $responsesByDonorId->get($alert->donor_id);
                $responseTimeSeconds = $this->notificationResponseTimeSeconds($alert->sent_at, $responseRecord?->responded_at);
                $responseStatus = $responseRecord && $responseRecord->responded_at && $alert->sent_at && $responseRecord->responded_at->greaterThanOrEqualTo($alert->sent_at)
                    ? Str::headline((string) $responseRecord->response)
                    : 'Pending';

                return [
                    'id' => 'alert-'.$alert->id,
                    'donor_id' => $alert->donor_id,
                    'donor_name' => $alert->donor?->name ?: 'Unknown donor',
                    'donor_code' => $alert->donor_id ? 'DNR-'.$alert->donor_id : null,
                    'channel' => $alert->channel === 'multi' ? 'MULTI' : Str::upper((string) $alert->channel),
                    'message_preview' => Str::limit($this->notificationDefaultMessagePreview($bloodRequest, $alert->donor), 120),
                    'notification_type' => 'emergency_blood_request',
                    'delivery_status' => $responseStatus !== 'Pending' ? 'Responded' : 'Sent',
                    'failure_reason' => null,
                    'retry_attempts' => 0,
                    'response_status' => $responseStatus,
                    'response_reason' => null,
                    'timestamp' => $alert->sent_at?->toISOString(),
                    'responded_at' => $responseRecord?->responded_at?->toISOString(),
                    'response_time_seconds' => $responseTimeSeconds,
                    'response_time_human' => $this->notificationResponseTimeHuman($responseTimeSeconds),
                    'speed_label' => $this->notificationSpeedLabel($responseTimeSeconds),
                ];
            })
            ->values();

        return $deliveryEntries
            ->merge($placeholderEntries)
            ->sortByDesc('timestamp')
            ->values()
            ->all();
    }

    private function buildNotificationSummary(BloodRequest $bloodRequest, $alerts, $responses, array $stream): array
    {
        $notifiedDonors = $alerts->pluck('donor_id')->filter()->unique()->count();
        $accepted = $responses->where('response', 'accepted')->count();
        $declined = $responses->where('response', 'declined')->count();
        $noResponse = max(0, $notifiedDonors - $responses->pluck('donor_id')->unique()->count());
        $timeoutThresholdMinutes = $this->notificationTimeoutThresholdMinutes($bloodRequest);
        $overdueNoResponse = $alerts
            ->whereNotIn('donor_id', $responses->pluck('donor_id')->all())
            ->filter(fn (DonorAlertLog $alert) => $alert->sent_at && $alert->sent_at->diffInMinutes(now()) >= $timeoutThresholdMinutes)
            ->count();

        $responseTimes = $alerts
            ->map(function (DonorAlertLog $alert) use ($responses) {
                $response = $responses->firstWhere('donor_id', $alert->donor_id);

                return $this->notificationResponseTimeSeconds($alert->sent_at, $response?->responded_at);
            })
            ->filter(fn ($seconds) => $seconds !== null)
            ->values();

        $avgResponseSeconds = $responseTimes->isNotEmpty() ? round($responseTimes->avg(), 2) : null;
        $acceptedRate = $notifiedDonors > 0 ? round(($accepted / $notifiedDonors) * 100, 2) : 0.0;
        $declinedRate = $notifiedDonors > 0 ? round(($declined / $notifiedDonors) * 100, 2) : 0.0;
        $noResponseRate = $notifiedDonors > 0 ? round(($noResponse / $notifiedDonors) * 100, 2) : 0.0;
        $health = $this->notificationHealth($acceptedRate, $avgResponseSeconds, $overdueNoResponse, $timeoutThresholdMinutes);

        return [
            'accepted' => [
                'count' => $accepted,
                'conversion_rate' => $acceptedRate,
                'health' => $health,
            ],
            'declined' => [
                'count' => $declined,
                'conversion_rate' => $declinedRate,
                'reasons' => [],
                'health' => $declinedRate >= 40 ? 'critical' : 'slow',
            ],
            'no_response' => [
                'count' => $noResponse,
                'conversion_rate' => $noResponseRate,
                'timeout_threshold_minutes' => $timeoutThresholdMinutes,
                'overdue_count' => $overdueNoResponse,
                'health' => $overdueNoResponse > 0 ? 'critical' : 'slow',
            ],
            'avg_response_time' => [
                'seconds' => $avgResponseSeconds,
                'human' => $this->notificationResponseTimeHuman($avgResponseSeconds),
                'health' => $avgResponseSeconds !== null && $avgResponseSeconds <= ($timeoutThresholdMinutes * 60) ? 'healthy' : 'slow',
            ],
            'total_notifications_sent' => count($stream),
            'response_health' => $health,
        ];
    }

    private function buildNotificationInsights($alerts, $responses, array $stream): array
    {
        $streamCollection = collect($stream);

        $mostResponsive = $streamCollection
            ->filter(fn (array $entry) => in_array(Str::lower((string) $entry['response_status']), ['accepted', 'declined'], true) && $entry['response_time_seconds'] !== null)
            ->sortBy('response_time_seconds')
            ->unique('donor_id')
            ->take(3)
            ->map(fn (array $entry) => [
                'donor_name' => $entry['donor_name'],
                'donor_code' => $entry['donor_code'],
                'response_time_human' => $entry['response_time_human'],
                'speed_label' => $entry['speed_label'],
            ])
            ->values()
            ->all();

        $leastResponsive = $streamCollection
            ->filter(fn (array $entry) => Str::lower((string) $entry['response_status']) === 'pending')
            ->sortByDesc(fn (array $entry) => $entry['timestamp'] ? strtotime((string) $entry['timestamp']) : 0)
            ->unique('donor_id')
            ->take(3)
            ->map(fn (array $entry) => [
                'donor_name' => $entry['donor_name'],
                'donor_code' => $entry['donor_code'],
                'last_contact_at' => $entry['timestamp'],
                'delivery_status' => $entry['delivery_status'],
            ])
            ->values()
            ->all();

        $reliabilityTrend = $alerts
            ->groupBy(fn (DonorAlertLog $alert) => (int) ($alert->escalation_level ?: 1))
            ->map(function ($levelAlerts, $level) use ($responses) {
                $notifiedIds = $levelAlerts->pluck('donor_id')->filter()->unique();
                $avgReliability = round($levelAlerts->avg(fn (DonorAlertLog $alert) => (float) ($alert->donor?->reliability_score ?? 0)), 2);
                $responded = $responses->whereIn('donor_id', $notifiedIds->all())->pluck('donor_id')->unique()->count();
                $responseRate = $notifiedIds->count() > 0 ? round(($responded / $notifiedIds->count()) * 100, 2) : 0.0;

                return [
                    'label' => 'Wave '.$level,
                    'average_reliability_score' => $avgReliability,
                    'response_rate' => $responseRate,
                ];
            })
            ->values()
            ->all();

        return [
            'most_responsive_donors' => $mostResponsive,
            'least_responsive_donors' => $leastResponsive,
            'reliability_trend' => $reliabilityTrend,
        ];
    }

    private function buildNotificationEscalationTriggers(BloodRequest $bloodRequest, $alerts, $activityLogs): array
    {
        $levelTriggers = $alerts
            ->where('escalation_level', '>', 1)
            ->groupBy('escalation_level')
            ->map(function ($group, $level) {
                $triggerCondition = match ((int) $level) {
                    2 => 'Low response detected → expanding radius',
                    3 => 'Emergency broadcast triggered',
                    default => 'Escalation triggered due to low donor engagement',
                };

                return [
                    'action' => $triggerCondition,
                    'trigger_condition' => match ((int) $level) {
                        2 => 'Low response rate after initial notification wave.',
                        3 => 'Escalation continued after repeated low donor engagement.',
                        default => 'System increased notification urgency.',
                    },
                    'time_triggered' => $group->sortBy('sent_at')->first()?->sent_at?->toISOString(),
                ];
            })
            ->values();

        $activityTriggers = $activityLogs
            ->filter(fn (ActivityLog $log) => in_array($log->action, [
                'notification.delivery.escalated',
                'past-match.radius-expanded',
                'past-match.emergency-mode-triggered',
                'notification.broadcast-all',
            ], true))
            ->map(function (ActivityLog $log) {
                $details = $log->details ?? [];

                return [
                    'action' => match ($log->action) {
                        'past-match.radius-expanded' => 'Low response detected → expanding radius',
                        'past-match.emergency-mode-triggered', 'notification.broadcast-all', 'notification.delivery.escalated' => 'Emergency broadcast triggered',
                        default => 'Escalation triggered',
                    },
                    'trigger_condition' => (string) ($details['reason'] ?? $details['trigger'] ?? 'Operational escalation event recorded.'),
                    'time_triggered' => $log->created_at?->toISOString(),
                ];
            });

        return $levelTriggers
            ->merge($activityTriggers)
            ->sortByDesc('time_triggered')
            ->values()
            ->all();
    }

    private function buildNotificationAnalytics(BloodRequest $bloodRequest, $alerts, $responses, array $stream): array
    {
        $streamCollection = collect($stream);
        $startTime = $bloodRequest->created_at->copy();

        $responseRateOverTime = $alerts
            ->sortBy('sent_at')
            ->groupBy(function (DonorAlertLog $alert) {
                $sentAt = $alert->sent_at ?? now();
                $minuteBucket = $sentAt->minute - ($sentAt->minute % 5);

                return $sentAt->copy()->setTime($sentAt->hour, $minuteBucket, 0)->format('H:i');
            })
            ->map(function ($bucketAlerts, $label) use ($responses, $startTime) {
                $bucketEnd = $startTime->copy()->setTimeFromTimeString($label.':00')->addMinutes(5);
                $notified = $bucketAlerts->pluck('donor_id')->filter()->unique()->count();
                $responded = $responses
                    ->filter(fn (DonorRequestResponse $response) => $response->responded_at && $response->responded_at->lessThanOrEqualTo($bucketEnd))
                    ->pluck('donor_id')
                    ->unique()
                    ->count();

                return [
                    'label' => $label,
                    'response_rate' => $notified > 0 ? round(($responded / $notified) * 100, 2) : 0.0,
                ];
            })
            ->values()
            ->all();

        $failedCount = $streamCollection->where('delivery_status', 'Failed')->count();
        $successfulCount = $streamCollection->whereIn('delivery_status', ['Delivered', 'Responded', 'Sent'])->count();
        $successRate = ($failedCount + $successfulCount) > 0
            ? round(($successfulCount / ($failedCount + $successfulCount)) * 100, 2)
            : 0.0;

        $channelEffectiveness = $streamCollection
            ->groupBy('channel')
            ->map(function ($entries, $channel) {
                $total = $entries->count();
                $delivered = $entries->whereIn('delivery_status', ['Delivered', 'Responded'])->count();
                $failed = $entries->where('delivery_status', 'Failed')->count();
                $responded = $entries->filter(fn (array $entry) => in_array(Str::lower((string) $entry['response_status']), ['accepted', 'declined'], true))->count();

                return [
                    'channel' => $channel,
                    'sent' => $total,
                    'failed' => $failed,
                    'responded' => $responded,
                    'success_rate' => $total > 0 ? round(($delivered / $total) * 100, 2) : 0.0,
                    'response_rate' => $total > 0 ? round(($responded / $total) * 100, 2) : 0.0,
                ];
            })
            ->values()
            ->all();

        return [
            'response_rate_over_time' => $responseRateOverTime,
            'notification_success_rate' => [
                'successful' => $successfulCount,
                'failed' => $failedCount,
                'success_rate' => $successRate,
            ],
            'channel_effectiveness' => $channelEffectiveness,
        ];
    }

    private function notificationDeliveryBelongsToRequest(NotificationDelivery $delivery, BloodRequest $bloodRequest, $alertsByUserId): bool
    {
        $requestId = (int) (data_get($delivery->response, 'payload.blood_request_id') ?? data_get($delivery->response, 'blood_request_id') ?? 0);

        if ($requestId === $bloodRequest->id) {
            return true;
        }

        if (! in_array($delivery->type, ['emergency_blood_request', 'request_reminder', 'manual_admin_message'], true)) {
            return false;
        }

        $userAlerts = $alertsByUserId->get($delivery->user_id, collect());
        $deliveryTime = $delivery->sent_at ?? $delivery->created_at;

        if (! $deliveryTime || $userAlerts->isEmpty()) {
            return false;
        }

        return $userAlerts->contains(fn (DonorAlertLog $alert) => $alert->sent_at && $alert->sent_at->diffInMinutes($deliveryTime) <= 20);
    }

    private function notificationDeliveryMatchedAlert(NotificationDelivery $delivery, $alertsByUserId): ?DonorAlertLog
    {
        $userAlerts = $alertsByUserId->get($delivery->user_id, collect());
        $deliveryTime = $delivery->sent_at ?? $delivery->created_at;

        return $userAlerts->first(fn (DonorAlertLog $alert) => $deliveryTime && $alert->sent_at && $alert->sent_at->diffInMinutes($deliveryTime) <= 20);
    }

    private function notificationTimeoutThresholdMinutes(BloodRequest $bloodRequest): int
    {
        return match (Str::lower((string) ($bloodRequest->urgency_level ?: 'medium'))) {
            'critical' => 5,
            'high' => 10,
            'low' => 30,
            default => 20,
        };
    }

    private function notificationDefaultMessagePreview(BloodRequest $bloodRequest, ?Donor $donor = null): string
    {
        return sprintf(
            'Emergency request for %s %s at %s. %s',
            $bloodRequest->blood_type,
            $bloodRequest->component ?: 'blood',
            $bloodRequest->hospital_name,
            $donor ? 'Donor '.$donor->name.' is being contacted for urgent support.' : 'Urgent donor response requested.'
        );
    }

    private function notificationFailureReason(NotificationDelivery $delivery): ?string
    {
        return data_get($delivery->response, 'reason')
            ?: data_get($delivery->response, 'exception')
            ?: data_get($delivery->response, 'response.message')
            ?: data_get($delivery->response, 'response.error.message');
    }

    private function notificationResponseTimeSeconds($sentAt, $respondedAt): ?int
    {
        if (! $sentAt || ! $respondedAt || $respondedAt->lessThan($sentAt)) {
            return null;
        }

        return (int) $sentAt->diffInSeconds($respondedAt);
    }

    private function notificationResponseTimeHuman($seconds): string
    {
        if ($seconds === null) {
            return 'Awaiting response';
        }

        if ($seconds < 60) {
            return $seconds.' sec';
        }

        return round($seconds / 60, 1).' min';
    }

    private function notificationSpeedLabel($seconds): string
    {
        if ($seconds === null) {
            return 'Pending';
        }

        if ($seconds <= 300) {
            return 'Fast responder';
        }

        if ($seconds >= 900) {
            return 'Slow responder';
        }

        return 'Standard';
    }

    private function notificationHealth(float $acceptedRate, $avgResponseSeconds, int $overdueCount, int $timeoutThresholdMinutes): string
    {
        if ($acceptedRate >= 40 && $overdueCount === 0) {
            return 'healthy';
        }

        if ($overdueCount > 0 || $acceptedRate < 20) {
            return 'critical';
        }

        if ($avgResponseSeconds !== null && $avgResponseSeconds > ($timeoutThresholdMinutes * 60)) {
            return 'slow';
        }

        return 'slow';
    }

    private function notificationElapsedHuman($createdAt): string
    {
        if (! $createdAt) {
            return 'Unknown';
        }

        $seconds = $createdAt->diffInSeconds(now());
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $hours > 0
            ? sprintf('%dh %dm', $hours, $minutes)
            : sprintf('%dm', $minutes);
    }

    private function notificationRequestStatus(BloodRequest $bloodRequest, $alerts): string
    {
        $fulfilledUnits = (int) ($bloodRequest->fulfilled_units ?? 0);
        $unitsRequired = (int) ($bloodRequest->units_required ?? $bloodRequest->requested_units ?? 0);

        if ($unitsRequired > 0 && $fulfilledUnits >= $unitsRequired) {
            return 'Fulfilled';
        }

        if ((int) ($alerts->max('escalation_level') ?? 1) > 1 || Str::lower((string) $bloodRequest->status) === 'escalated') {
            return 'Escalated';
        }

        return 'Matching';
    }

    private function buildHospitalIntelligence(Hospital $hospital): array
    {
        $activityMetrics = $this->hospitalActivityMetrics($hospital);
        $requests = $this->hospitalRequestsCollection($hospital);

        return [
            'id' => $hospital->id,
            'name' => $hospital->hospital_name,
            'location' => $hospital->location ?? $hospital->address,
            'status' => $hospital->status,
            'operational_status' => $this->hospitalOperationalStatus($hospital),
            'disabled' => $this->hospitalDisabled($hospital),
            'active_requests_count' => $activityMetrics['active_requests_count'],
            'critical_requests_count' => $activityMetrics['critical_requests_count'],
            'avg_response_time' => $activityMetrics['avg_response_time'],
            'last_activity' => $activityMetrics['last_activity'],
            'blood_types_needed' => $requests
                ->filter(fn (BloodRequest $request) => in_array($request->status, ['pending', 'matching'], true))
                ->pluck('blood_type')
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'active_request_urgencies' => $requests
                ->filter(fn (BloodRequest $request) => in_array($request->status, ['pending', 'matching'], true))
                ->pluck('urgency_level')
                ->filter()
                ->map(fn ($urgency) => Str::lower((string) $urgency))
                ->unique()
                ->values()
                ->all(),
            'reliability_score' => $this->hospitalReliabilityScore($hospital, $activityMetrics),
            'flags' => $this->hospitalRiskFlags($hospital, $activityMetrics),
        ];
    }

    private function hospitalRequestsCollection(Hospital $hospital)
    {
        return $hospital->bloodRequests()
            ->latest()
            ->get();
    }

    private function hospitalActivityMetrics(Hospital $hospital): array
    {
        $requests = $this->hospitalRequestsCollection($hospital);
        $totalRequests = $requests->count();
        $activeRequests = $requests->whereIn('status', ['pending', 'matching'])->count();
        $criticalRequests = $requests->filter(fn (BloodRequest $request) => in_array($request->status, ['pending', 'matching'], true)
            && ($request->urgency_level === 'critical' || $request->is_emergency))->count();
        $successfulMatches = $requests->whereIn('status', ['completed', 'fulfilled'])->count();
        $failedMatches = $requests->where('status', 'cancelled')->count();
        $avgResponseTime = $this->averageResponseTimeMinutesForHospital($hospital);
        $lastActivity = $this->hospitalLastActivity($hospital);

        return [
            'total_requests' => $totalRequests,
            'active_requests_count' => $activeRequests,
            'critical_requests_count' => $criticalRequests,
            'successful_matches' => $successfulMatches,
            'failed_matches' => $failedMatches,
            'avg_response_time' => $avgResponseTime,
            'last_activity' => $lastActivity,
            'cancellation_rate' => $totalRequests > 0 ? round(($failedMatches / $totalRequests) * 100, 2) : 0.0,
            'success_rate' => $totalRequests > 0 ? round(($successfulMatches / $totalRequests) * 100, 2) : 0.0,
        ];
    }

    private function hospitalOperationalStatus(Hospital $hospital): string
    {
        if ($this->hospitalDisabled($hospital)) {
            return 'idle';
        }

        $requests = $this->hospitalRequestsCollection($hospital);
        $hasCritical = $requests->contains(fn (BloodRequest $request) => in_array($request->status, ['pending', 'matching'], true)
            && ($request->urgency_level === 'critical' || $request->is_emergency));

        if ($hasCritical) {
            return 'critical';
        }

        $hasActive = $requests->contains(fn (BloodRequest $request) => in_array($request->status, ['pending', 'matching'], true));

        return $hasActive ? 'active' : 'idle';
    }

    private function hospitalReliabilityScore(Hospital $hospital, array $activityMetrics): float
    {
        $successRate = ((float) ($activityMetrics['success_rate'] ?? 0)) / 100;
        $cancellationRate = ((float) ($activityMetrics['cancellation_rate'] ?? 0)) / 100;
        $avgResponseTime = (float) ($activityMetrics['avg_response_time'] ?? 0);
        $responseSpeedScore = $avgResponseTime <= 0 ? 0.0 : max(0.0, 1.0 - ($avgResponseTime / 240));

        return round((($successRate * 60) + ($responseSpeedScore * 25) + ((1 - $cancellationRate) * 15)) * 100, 2);
    }

    private function hospitalRiskFlags(Hospital $hospital, array $activityMetrics): array
    {
        $flags = [];
        $totalRequests = (int) ($activityMetrics['total_requests'] ?? 0);
        $failedMatches = (int) ($activityMetrics['failed_matches'] ?? 0);
        $criticalRequests = (int) ($activityMetrics['critical_requests_count'] ?? 0);

        if ($totalRequests > 0 && ($failedMatches / $totalRequests) >= 0.35) {
            $flags[] = 'High failure rate';
        }

        if ($totalRequests >= 4 && ($criticalRequests >= 3 || ($criticalRequests / max(1, $totalRequests)) >= 0.5)) {
            $flags[] = 'Frequent urgent requests';
        }

        return $flags;
    }

    private function averageResponseTimeMinutesForHospital(Hospital $hospital): float
    {
        $base = DonorRequestResponse::query()
            ->join('blood_requests', 'blood_requests.id', '=', 'donor_request_responses.blood_request_id')
            ->where('blood_requests.hospital_id', $hospital->id)
            ->whereNotNull('donor_request_responses.responded_at');

        $raw = DB::connection()->getDriverName() === 'sqlite'
            ? $base->selectRaw('AVG((julianday(donor_request_responses.responded_at) - julianday(blood_requests.created_at)) * 24 * 60) as avg_minutes')->value('avg_minutes')
            : $base->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, blood_requests.created_at, donor_request_responses.responded_at)) as avg_minutes')->value('avg_minutes');

        return round((float) ($raw ?? 0), 2);
    }

    private function hospitalLastActivity(Hospital $hospital): ?string
    {
        $lastRequestAt = $hospital->bloodRequests()->max('updated_at');

        return optional($lastRequestAt ?? $hospital->updated_at)?->toISOString();
    }

    private function serializeHospitalRequest(BloodRequest $request): array
    {
        return [
            'id' => $request->id,
            'case_id' => $request->case_id,
            'blood_type' => $request->blood_type,
            'urgency_level' => $request->urgency_level,
            'status' => $request->status,
            'units_required' => $request->units_required,
            'city' => $request->city,
            'province' => $request->province,
            'created_at' => optional($request->created_at)?->toISOString(),
            'updated_at' => optional($request->updated_at)?->toISOString(),
            'is_emergency' => (bool) $request->is_emergency,
            'matched_donors_count' => (int) $request->matched_donors_count,
            'accepted_donors' => (int) $request->accepted_donors,
        ];
    }

    private function serializePastMatchRequestOption(BloodRequest $bloodRequest): array
    {
        $units = (int) ($bloodRequest->units_required ?: $bloodRequest->requested_units ?: 0);

        return [
            'id' => $bloodRequest->id,
            'case_id' => $bloodRequest->case_id,
            'label' => trim(sprintf(
                '%s • %s • %s • %d unit%s',
                $bloodRequest->case_id ?: 'Request #'.$bloodRequest->id,
                $bloodRequest->hospital_name ?: 'Unknown Hospital',
                $bloodRequest->blood_type ?: 'Unknown Blood Type',
                $units,
                $units === 1 ? '' : 's'
            )),
            'hospital_name' => $bloodRequest->hospital_name,
            'blood_type' => $bloodRequest->blood_type,
            'urgency_level' => $bloodRequest->urgency_level,
            'status' => $bloodRequest->status,
            'created_at' => optional($bloodRequest->created_at)?->toISOString(),
        ];
    }

    private function serializePastMatchRequestSummary(BloodRequest $bloodRequest, array $matchingState): array
    {
        $requiredUnits = (int) ($bloodRequest->units_required ?: $bloodRequest->requested_units ?: 0);
        $fulfilledUnits = (int) ($bloodRequest->fulfilled_units ?? 0);
        $timeRemainingSeconds = $this->pastMatchTimeRemainingSeconds($bloodRequest);

        return [
            'id' => $bloodRequest->id,
            'request_id' => $bloodRequest->case_id ?: 'REQ-'.$bloodRequest->id,
            'hospital_name' => $bloodRequest->hospital_name ?: $bloodRequest->hospital?->hospital_name ?: 'Unknown Hospital',
            'hospital_location' => trim(collect([$bloodRequest->city, $bloodRequest->province])->filter()->implode(', ')),
            'blood_type' => $bloodRequest->blood_type,
            'component' => $bloodRequest->component ?: 'Whole Blood',
            'units_required' => $requiredUnits,
            'fulfilled_units' => $fulfilledUnits,
            'units_completion_percentage' => $requiredUnits > 0 ? round(min(100, ($fulfilledUnits / $requiredUnits) * 100), 2) : 0.0,
            'urgency_level' => $bloodRequest->urgency_level ?: 'medium',
            'status' => $bloodRequest->status,
            'matching_status' => $matchingState['matching_status'],
            'matching_phase' => $matchingState['phase_label'],
            'time_created' => optional($bloodRequest->created_at)?->toISOString(),
            'created_at' => optional($bloodRequest->created_at)?->toISOString(),
            'expires_at' => $bloodRequest->expiry_time?->toISOString(),
            'time_remaining_seconds' => $timeRemainingSeconds,
            'distance_limit_km' => round((float) ($bloodRequest->distance_limit_km ?? DonorFilterService::DEFAULT_DISTANCE_LIMIT_KM), 2),
            'city' => $bloodRequest->city,
            'province' => $bloodRequest->province,
            'notifications_sent' => (int) ($bloodRequest->notifications_sent ?? 0),
            'responses_received' => (int) ($bloodRequest->responses_received ?? 0),
            'accepted_donors' => (int) ($bloodRequest->accepted_donors ?? 0),
            'notifications_paused' => $matchingState['notifications_paused'],
            'emergency_mode_active' => $matchingState['emergency_mode_active'],
        ];
    }

    private function buildPastMatchRankedDonors(BloodRequest $bloodRequest, $rankedCandidates, $matches, $alerts, $responses)
    {
        $matchesByDonorId = $matches->keyBy('donor_id');
        $alertsByDonorId = $alerts->groupBy('donor_id');
        $responsesByDonorId = $responses->keyBy('donor_id');
        $urgencyPressure = match (Str::lower((string) ($bloodRequest->urgency_level ?? 'medium'))) {
            'critical' => 100.0,
            'high' => 85.0,
            'medium' => 65.0,
            default => 45.0,
        };

        return $rankedCandidates->map(function (array $candidate, int $index) use ($bloodRequest, $matchesByDonorId, $alertsByDonorId, $responsesByDonorId, $urgencyPressure) {
            $donor = $candidate['donor'];
            $alertEntries = $alertsByDonorId->get($donor->id, collect());
            $responseEntry = $responsesByDonorId->get($donor->id);
            $storedMatch = $matchesByDonorId->get($donor->id);
            $eligibilityLabel = $donor->isEligibleForDonation()
                ? 'Eligible'
                : 'Cooldown until '.$donor->nextEligibleDonationDate()?->toDateString();
            $auditScores = $candidate['audit_scores'] ?? app(PASTMatch::class)->buildGroupedAuditScores($candidate['factors'] ?? [], $bloodRequest->urgency_level);
            $priorityScore = round((float) ($auditScores['priority'] ?? 0), 2);
            $availabilityScore = round((float) ($auditScores['availability'] ?? 0), 2);
            $distanceScore = round((float) ($auditScores['distance'] ?? 0), 2);
            $timeScore = round((float) ($auditScores['time'] ?? 0), 2);
            $finalScore = round((float) ($auditScores['final'] ?? 0), 2);
            $operationalScore = round((float) ($storedMatch?->score ?? $candidate['operational_score'] ?? $candidate['score'] ?? $finalScore), 2);
            $emergencyAdjustment = round((float) ($candidate['emergency_adjustment'] ?? max(0, $operationalScore - $finalScore)), 2);
            $cooldownPenalty = round((float) ($candidate['cooldown_penalty'] ?? 0), 2);
            $weights = $auditScores['weights'] ?? app(SystemSettingsService::class)->pastMatchWeights();
            $responseStatus = $responseEntry
                ? Str::headline((string) $responseEntry->response)
                : ($alertEntries->isNotEmpty() || $storedMatch ? 'Pending' : 'Queued');

            return [
                'rank' => (int) ($storedMatch?->rank ?? ($index + 1)),
                'donor_id' => $donor->id,
                'donor_name' => $donor->name,
                'donor_label' => $donor->name.' (#'.$donor->id.')',
                'blood_type' => $donor->blood_type,
                'blood_type_compatibility' => Str::upper((string) $donor->blood_type) === Str::upper((string) $bloodRequest->blood_type) ? 'Exact match' : 'Compatible match',
                'distance_km' => $candidate['distance_km'] !== null ? round((float) $candidate['distance_km'], 2) : null,
                'last_donation_date' => optional($donor->last_donation_date)?->toDateString(),
                'eligibility_label' => $eligibilityLabel,
                'availability_status' => $donor->availability ? 'Available' : 'Unavailable',
                'reliability_score' => round((float) ($donor->reliability_score ?? 0), 2),
                'reliability_label' => $donor->reliabilityLabel(),
                'response_status' => $responseStatus,
                'status' => $responseStatus,
                'compatibility_score' => $finalScore,
                'emergency_adjustment' => $emergencyAdjustment,
                'cooldown_penalty' => $cooldownPenalty,
                'operational_score' => $operationalScore,
                'score_breakdown' => [
                    'priority' => ['value' => $priorityScore, 'weight' => $weights['priority'], 'contribution' => round($priorityScore * $weights['priority'], 2), 'explanation' => 'Urgency pressure and donor readiness determine whether this donor should be prioritized immediately.'],
                    'availability' => ['value' => $availabilityScore, 'weight' => $weights['availability'], 'contribution' => round($availabilityScore * $weights['availability'], 2), 'explanation' => 'Availability, donation interval eligibility, and donor reliability estimate whether the donor can safely respond.'],
                    'distance' => ['value' => $distanceScore, 'weight' => $weights['distance'], 'contribution' => round($distanceScore * $weights['distance'], 2), 'explanation' => 'Distance and transport accessibility reflect feasibility within the active radius.'],
                    'time' => ['value' => $timeScore, 'weight' => $weights['time'], 'contribution' => round($timeScore * $weights['time'], 2), 'explanation' => 'ETA, traffic, and fastest-arrival signals capture time-critical fit for this request.'],
                    'final' => ['value' => $finalScore, 'weight' => 1.0, 'contribution' => $finalScore, 'explanation' => 'Normalized compatibility score used for explainability and settings-based audit review.'],
                    'emergency_adjustment' => ['value' => $emergencyAdjustment, 'weight' => null, 'contribution' => $emergencyAdjustment, 'explanation' => 'Emergency mode adds an operational uplift for fastest-response donors without changing the normalized compatibility score.'],
                    'cooldown_penalty' => ['value' => $cooldownPenalty, 'weight' => null, 'contribution' => -$cooldownPenalty, 'explanation' => 'Fairness rotation: donors matched within the last 72 hours receive a small operational deduction so that high-reliability donors do not monopolise every request queue.'],
                    'operational' => ['value' => $operationalScore, 'weight' => null, 'contribution' => $operationalScore, 'explanation' => 'Composite queueing score used to rank donors while emergency mode is active.'],
                ],
                'raw_signals' => [
                    'proximity' => round((float) ($candidate['factors']['proximity'] ?? 0), 2),
                    'availability' => round((float) ($candidate['factors']['availability'] ?? 0), 2),
                    'donation_interval' => round((float) ($candidate['factors']['donation_interval'] ?? 0), 2),
                    'travel_time' => round((float) ($candidate['factors']['travel_time'] ?? 0), 2),
                    'traffic' => round((float) ($candidate['factors']['traffic'] ?? 0), 2),
                    'accessibility' => round((float) ($candidate['factors']['accessibility'] ?? 0), 2),
                    'arrival_priority' => round((float) ($candidate['factors']['arrival_priority'] ?? 0), 2),
                ],
                'metrics' => [
                    'estimated_travel_minutes' => round((float) ($candidate['estimated_travel_minutes'] ?? 0), 2),
                    'traffic_condition' => (string) ($candidate['traffic_condition'] ?? 'unknown'),
                    'transport_accessibility_score' => round((float) ($candidate['transport_accessibility_score'] ?? 0), 2),
                    'fastest_arrival_score' => round((float) ($candidate['fastest_arrival_score'] ?? 0), 2),
                    'location_source' => (string) ($candidate['location_source'] ?? 'unknown'),
                    'location_confidence' => round((float) ($candidate['location_confidence'] ?? 0), 2),
                ],
                'timeline' => [
                    'notified_at' => $alertEntries->first()?->sent_at?->toISOString(),
                    'responded_at' => $responseEntry?->responded_at?->toISOString(),
                ],
            ];
        })->sortBy('rank')->values();
    }

    private function buildPastMatchTimeline(BloodRequest $bloodRequest, $matches, $alerts, $responses, $activityLogs): array
    {
        $createdAt = $bloodRequest->created_at;
        $matchingProcessedAt = $activityLogs->firstWhere('action', 'blood-request.matching-processed')?->created_at;
        if (! $matchingProcessedAt && $matches->isNotEmpty()) {
            $matchingProcessedAt = $matches->min('created_at');
        }
        $matchingProcessedAt ??= $createdAt;

        $rankedAt = $matches->isNotEmpty() ? $matches->max('created_at') : $matchingProcessedAt;
        $notifiedAt = $alerts->min('sent_at');
        $respondedAt = $responses->min('responded_at');

        $stages = [
            ['key' => 'request_created', 'label' => 'Request Created', 'timestamp' => $createdAt],
            ['key' => 'algorithm_triggered', 'label' => 'Algorithm Triggered', 'timestamp' => $matchingProcessedAt],
            ['key' => 'donors_ranked', 'label' => 'Donors Ranked', 'timestamp' => $rankedAt],
            ['key' => 'notifications_sent', 'label' => 'Notifications Sent', 'timestamp' => $notifiedAt],
            ['key' => 'responses_received', 'label' => 'Responses Received', 'timestamp' => $respondedAt],
        ];

        $previousTimestamp = null;

        return collect($stages)->map(function (array $stage) use ($createdAt, &$previousTimestamp) {
            $timestamp = $stage['timestamp'];
            $delayMinutes = $timestamp && $previousTimestamp
                ? $previousTimestamp->diffInMinutes($timestamp)
                : 0;
            $offsetMinutes = $timestamp && $createdAt
                ? $createdAt->diffInMinutes($timestamp)
                : null;
            $delayed = match ($stage['key']) {
                'algorithm_triggered' => $offsetMinutes !== null && $offsetMinutes > 1,
                'notifications_sent' => $offsetMinutes !== null && $offsetMinutes > 5,
                'responses_received' => $offsetMinutes !== null && $offsetMinutes > 30,
                default => false,
            };

            if ($timestamp) {
                $previousTimestamp = $timestamp;
            }

            return [
                'key' => $stage['key'],
                'label' => $stage['label'],
                'timestamp' => $timestamp?->toISOString(),
                'delay_minutes' => $delayMinutes,
                'offset_minutes' => $offsetMinutes,
                'completed' => $timestamp !== null,
                'delayed' => $delayed,
            ];
        })->values()->all();
    }

    private function resolvePastMatchCurrentStage(array $timeline): string
    {
        return collect($timeline)
            ->filter(fn (array $stage) => $stage['completed'])
            ->last()['key'] ?? 'request_created';
    }

    private function buildPastMatchMatchingState(BloodRequest $bloodRequest, $alerts, $responses, array $escalation): array
    {
        $requiredUnits = (int) ($bloodRequest->units_required ?: $bloodRequest->requested_units ?: 0);
        $fulfilledUnits = (int) ($bloodRequest->fulfilled_units ?? 0);
        $notifiedDonors = $alerts->pluck('donor_id')->unique()->count();
        $respondedDonors = $responses->count();
        $acceptedDonors = max((int) ($bloodRequest->accepted_donors ?? 0), $responses->where('response', 'accepted')->count());
        $currentLevel = (int) ($escalation['current_level'] ?? 1);
        $notificationsPaused = $this->pastMatchControlState($bloodRequest)['notifications_paused'];
        $emergencyModeActive = app(EmergencyBroadcastModeService::class)->isActive();
        $responseRate = $notifiedDonors > 0 ? round(($respondedDonors / $notifiedDonors) * 100, 2) : 0.0;
        $efficiency = $requiredUnits > 0 ? round(min(100, ($fulfilledUnits / $requiredUnits) * 100), 2) : 0.0;

        $phaseLabel = match (true) {
            in_array($bloodRequest->status, ['completed', 'fulfilled'], true) || ($requiredUnits > 0 && $fulfilledUnits >= $requiredUnits) => 'Fulfilled',
            $currentLevel >= 3 => 'Escalation Phase 3',
            $currentLevel === 2 => 'Escalation Phase 2',
            $currentLevel === 1 && $alerts->isNotEmpty() && $responses->isEmpty() => 'Waiting for Response',
            $alerts->isNotEmpty() => 'Escalation Phase 1',
            default => 'Initial Matching',
        };

        $matchingStatus = match (true) {
            in_array($bloodRequest->status, ['completed', 'fulfilled'], true) || ($requiredUnits > 0 && $fulfilledUnits >= $requiredUnits) => 'Fulfilled',
            $currentLevel > 1 => 'Escalated',
            $alerts->isNotEmpty() || $bloodRequest->status === 'matching' => 'Matching',
            default => 'Pending',
        };

        $activeRadius = collect($escalation['levels'] ?? [])->firstWhere('level', $currentLevel)['radius_km'] ?? round((float) ($bloodRequest->distance_limit_km ?? DonorFilterService::DEFAULT_DISTANCE_LIMIT_KM), 2);

        return [
            'phase_label' => $phaseLabel,
            'matching_status' => $matchingStatus,
            'active_radius_km' => $activeRadius,
            'total_donors_notified' => $notifiedDonors,
            'response_rate_percentage' => $responseRate,
            'accepted_donors' => $acceptedDonors,
            'required_units' => $requiredUnits,
            'fulfilled_units' => $fulfilledUnits,
            'matching_efficiency_percentage' => $efficiency,
            'notifications_paused' => $notificationsPaused,
            'emergency_mode_active' => $emergencyModeActive,
            'sync_status' => $notificationsPaused ? 'paused' : 'synced',
        ];
    }

    private function buildPastMatchEscalation(BloodRequest $bloodRequest, $rankedCandidates, $alerts, $responses): array
    {
        $candidateDistanceMap = $rankedCandidates
            ->mapWithKeys(fn (array $candidate) => [
                $candidate['donor']->id => $candidate['distance_km'] !== null ? round((float) $candidate['distance_km'], 2) : null,
            ]);

        $levels = collect([1, 2, 3])->map(function (int $level) use ($alerts, $candidateDistanceMap, $bloodRequest) {
            $levelAlerts = $alerts->where('escalation_level', $level)->values();
            $donorIds = $levelAlerts->pluck('donor_id')->unique()->values();
            $distances = $levelAlerts
                ->pluck('donor_id')
                ->map(fn ($donorId) => $candidateDistanceMap->get($donorId))
                ->filter(fn ($distance) => $distance !== null)
                ->values();
            $levelResponses = DonorRequestResponse::query()
                ->where('blood_request_id', $bloodRequest->id)
                ->whereIn('donor_id', $donorIds->all())
                ->get();

            return [
                'level' => $level,
                'label' => match ($level) {
                    1 => 'Initial outreach',
                    2 => 'Expanded radius',
                    default => 'High-priority escalation',
                },
                'radius_km' => $distances->isNotEmpty()
                    ? round((float) $distances->max(), 2)
                    : $this->pastMatchProjectedRadiusForLevel($bloodRequest, $level),
                'notified_donors' => $donorIds->count(),
                'responded_donors' => $levelResponses->count(),
                'accepted_donors' => $levelResponses->where('response', 'accepted')->count(),
                'trigger_condition' => match ($level) {
                    1 => 'Request created and initial donor shortlist generated.',
                    2 => 'Low response rate after the first notification burst.',
                    default => 'No accepted donor after wider-radius escalation.',
                },
                'first_triggered_at' => $levelAlerts->first()?->sent_at?->toISOString(),
                'active' => $levelAlerts->isNotEmpty(),
                'donor_ids' => $donorIds->all(),
            ];
        })->values();

        $currentLevel = max(1, (int) ($alerts->max('escalation_level') ?? 1));

        return [
            'current_level' => $currentLevel,
            'adaptive' => $currentLevel > 1,
            'no_donor_response' => ! $responses->contains('response', 'accepted'),
            'additional_donors_notified' => max(0, $alerts->pluck('donor_id')->unique()->count() - $alerts->where('escalation_level', 1)->pluck('donor_id')->unique()->count()),
            'levels' => $levels->all(),
        ];
    }

    private function pastMatchProjectedRadiusForLevel(BloodRequest $bloodRequest, int $level): float
    {
        $base = (float) ($bloodRequest->distance_limit_km ?? DonorFilterService::DEFAULT_DISTANCE_LIMIT_KM);

        return match ($level) {
            1 => round($base, 2),
            2 => round(min(500.0, max($base * 2, $base + 25)), 2),
            default => 500.0,
        };
    }

    private function buildPastMatchEscalationTimeline(BloodRequest $bloodRequest, array $escalation, EmergencyBroadcastModeService $emergencyBroadcastModeService): array
    {
        $createdAt = $bloodRequest->created_at;
        $entries = collect($escalation['levels'] ?? [])->map(function (array $level) use ($createdAt, $bloodRequest) {
            $timestamp = $level['first_triggered_at'] ? now()->parse((string) $level['first_triggered_at']) : ($level['level'] === 1 ? $createdAt : null);

            return [
                'timestamp' => $timestamp?->toISOString(),
                'offset_minutes' => $timestamp && $createdAt ? $createdAt->diffInMinutes($timestamp) : 0,
                'action_taken' => match ($level['level']) {
                    1 => 'Initial '.round((float) ($bloodRequest->distance_limit_km ?? DonorFilterService::DEFAULT_DISTANCE_LIMIT_KM), 2).'km radius launched',
                    2 => 'Expanded matching radius to '.$level['radius_km'].'km',
                    default => 'Escalated to high-priority compatible donor pool',
                },
                'trigger_condition' => $level['trigger_condition'],
                'radius_km' => $level['radius_km'],
            ];
        });

        if ($emergencyBroadcastModeService->isActive()) {
            $state = $emergencyBroadcastModeService->state();
            $activatedAt = ! empty($state['activated_at']) ? now()->parse((string) $state['activated_at']) : null;

            $entries->push([
                'timestamp' => $activatedAt?->toISOString(),
                'offset_minutes' => $activatedAt && $createdAt ? $createdAt->diffInMinutes($activatedAt) : null,
                'action_taken' => 'Emergency broadcast mode triggered',
                'trigger_condition' => $state['trigger'] ?? 'Manual admin override',
                'radius_km' => 500.0,
            ]);
        }

        return $entries->filter(fn (array $entry) => $entry['timestamp'] !== null)->sortBy('timestamp')->values()->all();
    }

    private function buildPastMatchAnalytics(BloodRequest $bloodRequest, $alerts, $responses, array $escalation, array $matchingState): array
    {
        $createdAt = $bloodRequest->created_at;
        $events = $alerts->map(fn (DonorAlertLog $alert) => ['type' => 'alert', 'at' => $alert->sent_at])
            ->merge($responses->map(fn (DonorRequestResponse $response) => ['type' => 'response', 'at' => $response->responded_at]))
            ->filter(fn (array $entry) => $entry['at'] !== null)
            ->sortBy('at')
            ->values();

        $notified = 0;
        $responded = 0;
        $series = collect([['label' => 'T+0m', 'response_rate' => 0, 'notified' => 0, 'responded' => 0]]);

        foreach ($events as $event) {
            if ($event['type'] === 'alert') {
                $notified++;
            }

            if ($event['type'] === 'response') {
                $responded++;
            }

            $series->push([
                'label' => 'T+'.($createdAt ? $createdAt->diffInMinutes($event['at']) : 0).'m',
                'response_rate' => $notified > 0 ? round(($responded / $notified) * 100, 2) : 0.0,
                'notified' => $notified,
                'responded' => $responded,
            ]);
        }

        return [
            'response_rate_series' => $series->all(),
            'donor_engagement' => collect($escalation['levels'] ?? [])->map(fn (array $level) => [
                'label' => 'Level '.$level['level'],
                'notified' => $level['notified_donors'],
                'responded' => $level['responded_donors'],
                'accepted' => $level['accepted_donors'],
            ])->values()->all(),
            'matching_efficiency' => [
                'fulfilled_percentage' => $matchingState['matching_efficiency_percentage'],
                'response_rate_percentage' => $matchingState['response_rate_percentage'],
                'accepted_vs_required_percentage' => $matchingState['required_units'] > 0 ? round(min(100, ($matchingState['accepted_donors'] / $matchingState['required_units']) * 100), 2) : 0.0,
            ],
        ];
    }

    private function pastMatchControlState(BloodRequest $bloodRequest): array
    {
        $state = Cache::get($this->pastMatchControlCacheKey($bloodRequest), []);

        return [
            'notifications_paused' => (bool) ($state['notifications_paused'] ?? false),
            'updated_at' => $state['updated_at'] ?? null,
        ];
    }

    private function pastMatchControlCacheKey(BloodRequest $bloodRequest): string
    {
        return 'past-match:control:'.$bloodRequest->id;
    }

    private function pastMatchTimeRemainingSeconds(BloodRequest $bloodRequest): ?int
    {
        if (! $bloodRequest->expiry_time) {
            return null;
        }

        return max(0, now()->diffInSeconds($bloodRequest->expiry_time, false));
    }

    private function buildPastMatchActivityFeed(BloodRequest $bloodRequest, $alerts, $responses, $activityLogs)
    {
        $entries = collect([
            [
                'timestamp' => $bloodRequest->created_at?->toISOString(),
                'type' => 'request',
                'message' => sprintf('%s created for %s blood', $bloodRequest->case_id ?: 'Request #'.$bloodRequest->id, $bloodRequest->blood_type ?: 'unknown'),
            ],
        ]);

        $alertEntries = $alerts->map(fn (DonorAlertLog $alert) => [
            'timestamp' => $alert->sent_at?->toISOString(),
            'type' => 'notification',
            'message' => sprintf('Donor #%d notified at escalation level %d', $alert->donor_id, (int) $alert->escalation_level),
        ]);

        $responseEntries = $responses->map(fn (DonorRequestResponse $response) => [
            'timestamp' => $response->responded_at?->toISOString(),
            'type' => 'response',
            'message' => sprintf('Donor #%d %s', $response->donor_id, strtolower((string) $response->response) === 'accepted' ? 'accepted' : 'responded'),
        ]);

        $activityEntries = $activityLogs->map(function (ActivityLog $log) {
            $message = match ($log->action) {
                'blood-request.matching-processed' => 'Algorithm ranked donors for the request',
                'blood-request.matching-failed' => 'Matching process failed',
                'notification.delivery.escalated' => 'Escalation triggered for donor outreach',
                'past-match.radius-expanded' => 'Admin manually expanded the active radius',
                'past-match.matching-rerun' => 'Admin re-ran matching for the request',
                'past-match.notifications-paused' => 'Notifications paused for this request',
                'past-match.notifications-resumed' => 'Notifications resumed for this request',
                'past-match.emergency-mode-triggered' => 'Emergency mode was triggered for this request',
                'past-match.notifications-paused-skip' => 'Notification dispatch skipped because notifications are paused',
                default => Str::headline(str_replace(['.', '-'], ' ', $log->action)),
            };

            return [
                'timestamp' => $log->created_at?->toISOString(),
                'type' => 'system',
                'message' => $message,
            ];
        });

        return $entries
            ->merge($activityEntries)
            ->merge($alertEntries)
            ->merge($responseEntries)
            ->filter(fn (array $entry) => $entry['timestamp'] !== null)
            ->sortByDesc('timestamp')
            ->values();
    }

    private function pastMatchFormulaPayload(SystemSettingsService $systemSettingsService, ?string $urgencyLevel = null): array
    {
        $resolvedUrgency = strtolower(trim((string) ($urgencyLevel ?? 'medium')));
        $weights = $systemSettingsService->pastMatchWeights($resolvedUrgency);

        return [
            'label' => 'PAST-Match Base Audit Formula',
            'expression' => $systemSettingsService->formatWeightExpression($resolvedUrgency),
            'weights' => $weights,
            'active_profile' => $resolvedUrgency,
            'profiles' => $systemSettingsService->pastMatchWeightProfiles(),
            'tooltips' => [
                'priority' => 'Reflects request urgency pressure and donor readiness for high-priority allocation.',
                'availability' => 'Combines live availability with donation interval eligibility and donor reliability.',
                'distance' => 'Measures proximity to the request and transport accessibility within the active radius.',
                'time' => 'Captures fastest arrival, ETA, and traffic-adjusted travel conditions.',
            ],
            'note' => 'Grouped audit scoring is configurable from admin settings, then adapted per urgency tier so critical requests shift toward time and priority without changing the baseline configuration surface.',
        ];
    }

    private function hospitalDisabled(Hospital $hospital): bool
    {
        return (bool) data_get(Cache::get($this->hospitalStatusCacheKey($hospital), []), 'disabled', false);
    }

    private function hospitalStatusCacheKey(Hospital $hospital): string
    {
        return 'admin:hospital-status:'.$hospital->id;
    }

    public function hospitalInviteCodes(Request $request): JsonResponse
    {
        $perPage = min(100, max(5, (int) $request->integer('per_page', 20)));

        return response()->json(
            HospitalInviteCode::query()
                ->latest()
                ->paginate($perPage)
        );
    }

    public function createHospitalInviteCode(Request $request, HospitalInviteCodeService $inviteCodes): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'expires_in_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
        ]);

        $issued = $inviteCodes->issue(
            email: $validated['email'] ?? null,
            domain: $validated['domain'] ?? null,
            expiresAt: isset($validated['expires_in_hours']) ? now()->addHours((int) $validated['expires_in_hours']) : null,
            issuedByUserId: $request->user()?->id,
        );

        ActivityLog::record($request->user()?->id, 'hospital.invite_code.created', [
            'invite_code_id' => $issued['invite']->id,
            'email' => $issued['invite']->email,
            'domain' => $issued['invite']->domain,
            'expires_at' => $issued['invite']->expires_at,
        ]);

        return response()->json([
            'message' => 'Hospital invite code created.',
            'data' => [
                'id' => $issued['invite']->id,
                'code' => $issued['code'],
                'email' => $issued['invite']->email,
                'domain' => $issued['invite']->domain,
                'expires_at' => $issued['invite']->expires_at,
            ],
        ], 201);
    }

    public function revokeHospitalInviteCode(Request $request, HospitalInviteCode $hospitalInviteCode): JsonResponse
    {
        $hospitalInviteCode->forceFill([
            'revoked_at' => now(),
            'revoked_by_user_id' => $request->user()?->id,
        ])->save();

        ActivityLog::record($request->user()?->id, 'hospital.invite_code.revoked', [
            'invite_code_id' => $hospitalInviteCode->id,
        ]);

        return response()->json([
            'message' => 'Hospital invite code revoked.',
            'data' => $hospitalInviteCode,
        ]);
    }
}
