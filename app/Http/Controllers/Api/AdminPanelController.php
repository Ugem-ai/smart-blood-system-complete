<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\BloodRequest;
use App\Models\Donor;
use App\Models\NationalPartnerSyncLog;
use App\Models\DonorRequestResponse;
use App\Models\HospitalInviteCode;
use App\Models\RequestMatch;
use App\Models\Hospital;
use App\Models\User;
use App\Services\BloodSupplyForecastService;
use App\Services\DonorNotificationTimingService;
use App\Services\EmergencyBroadcastModeService;
use App\Services\EmergencyDashboardService;
use App\Services\HospitalInviteCodeService;
use App\Services\InventoryMonitoringService;
use App\Services\NationalSystemsIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AdminPanelController extends Controller
{
    public function dashboard(
        Request $request,
        BloodSupplyForecastService $forecastService,
        InventoryMonitoringService $inventoryService,
        EmergencyBroadcastModeService $emergencyBroadcastModeService,
        DonorNotificationTimingService $timingService
    ): JsonResponse
    {
        $forecastMonths = max(3, min(12, (int) $request->integer('forecast_months', 6)));

        $totalRequests = BloodRequest::query()->count();
        $completedRequests = BloodRequest::query()->where('status', 'completed')->count();
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

    public function setEmergencyMode(Request $request, EmergencyBroadcastModeService $emergencyBroadcastModeService): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'trigger' => ['nullable', 'string', 'max:255'],
        ]);

        $state = (bool) $validated['enabled']
            ? $emergencyBroadcastModeService->activate($validated['trigger'] ?? null, $request->user()?->id)
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

        return response()->json(
            BloodRequest::query()->latest()->paginate($perPage)
        );
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

        return response()->json(
            Hospital::query()->latest()->paginate($perPage)
        );
    }

    public function donors(Request $request): JsonResponse
    {
        $perPage = min(100, max(5, (int) $request->integer('per_page', 20)));

        return response()->json(
            Donor::query()->latest()->paginate($perPage)
        );
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $perPage = min(100, max(5, (int) $request->integer('per_page', 20)));

        return response()->json(
            ActivityLog::query()
                ->leftJoin('users', 'users.id', '=', 'activity_logs.actor_user_id')
                ->select('activity_logs.*', 'users.name as user_name')
                ->latest('activity_logs.created_at')
                ->paginate($perPage)
        );
    }

    public function analytics(Request $request): JsonResponse
    {
        $range = in_array($request->input('range'), ['daily', 'weekly', 'monthly'])
            ? $request->input('range')
            : 'weekly';

        $days = match ($range) {
            'daily' => 7,
            'monthly' => 180,
            default => 42,
        };

        $bloodDemandTrends = BloodRequest::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count')
            ->values();

        $mostRequestedBloodTypes = BloodRequest::query()
            ->selectRaw('blood_type, COUNT(*) as count')
            ->groupBy('blood_type')
            ->orderByDesc('count')
            ->pluck('count')
            ->values();

        $donorResponseRates = DonorRequestResponse::query()
            ->where('response', 'accepted')
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count')
            ->values();

        $totalRequests = BloodRequest::query()->count();
        $completedRequests = BloodRequest::query()->where('status', 'completed')->count();
        $successRate = $totalRequests > 0 ? round(($completedRequests / $totalRequests) * 100, 1) : 0.0;

        return response()->json([
            'data' => [
                'blood_demand_trends' => $bloodDemandTrends,
                'most_requested_blood_types' => $mostRequestedBloodTypes,
                'donor_response_rates' => $donorResponseRates,
                'matching_success_rate' => [$successRate],
            ],
        ]);
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

    public function updateRequest(Request $request, BloodRequest $bloodRequest): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'required', 'string', 'in:pending,matched,confirmed,completed,cancelled'],
            'urgency_level' => ['sometimes', 'required', 'string', 'in:low,medium,high,critical'],
        ]);

        $bloodRequest->update($validated);

        ActivityLog::record($request->user()?->id, 'request.updated', [
            'blood_request_id' => $bloodRequest->id,
            'changes' => $validated,
        ]);

        return response()->json(['message' => 'Request updated.', 'data' => $bloodRequest]);
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
