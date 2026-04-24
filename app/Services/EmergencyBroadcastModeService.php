<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\BloodRequest;
use App\Models\DonationHistory;
use App\Models\Donor;
use App\Models\DonorAlertLog;
use App\Models\DonorRequestResponse;
use App\Models\EmergencyState;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class EmergencyBroadcastModeService
{
    private const CACHE_KEY = 'system:emergency_broadcast_mode:v1';
    private const SINGLETON_STATE_ID = 1;

    public const BROADCAST_ESCALATION_LEVEL = 99;

    private const DEFAULT_DISASTER_TRIGGERS = [
        'earthquake',
        'major accident',
        'large-scale emergency',
    ];

    /**
     * @return array{enabled: bool, trigger: string|null, activated_at: string|null, activated_by: int|null, triggered_at: string|null, triggered_by: int|null, expires_at: string|null, active_duration_seconds: int}
     */
    public function state(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached)) {
            $normalized = $this->normalizeState($cached);

            if ($this->hasExpired($normalized)) {
                $this->expireExpiredState();

                return $this->state();
            }

            return $normalized;
        }

        $normalized = $this->stateFromRecord($this->currentStateRecord());

        if ($this->hasExpired($normalized)) {
            $this->expireExpiredState();

            return $this->state();
        }

        $this->syncCache($normalized);

        return $normalized;
    }

    public function isActive(): bool
    {
        return $this->state()['enabled'];
    }

    public function isDisasterResponseActive(): bool
    {
        $state = $this->state();

        if (! (bool) ($state['enabled'] ?? false)) {
            return false;
        }

        $trigger = $this->normalizeTrigger($state['trigger'] ?? null);

        if ($trigger === null) {
            return false;
        }

        return in_array($trigger, $this->disasterTriggers(), true);
    }

    /**
     * @return array{active: bool, trigger: string|null, force_priority_requests: bool, expanded_radius_km: int, mass_notification: bool, expires_at: string|null}
     */
    public function disasterResponseState(): array
    {
        $state = $this->state();

        return [
            'active' => $this->isDisasterResponseActive(),
            'trigger' => $state['trigger'],
            'force_priority_requests' => (bool) config('services.notifications.disaster_force_priority_requests', true),
            'expanded_radius_km' => $this->expandedRadiusKm(),
            'mass_notification' => $this->isActive(),
            'expires_at' => $state['expires_at'],
        ];
    }

    public function applyPriorityUrgency(string $requestedUrgency): string
    {
        if (! $this->isActive()) {
            return $requestedUrgency;
        }

        if (! (bool) config('services.notifications.disaster_force_priority_requests', true)) {
            return $requestedUrgency;
        }

        return 'high';
    }

    public function applyExpandedRadius(?int $requestedDistanceLimitKm): int
    {
        $defaultDistance = DonorFilterService::DEFAULT_DISTANCE_LIMIT_KM;
        $requested = $requestedDistanceLimitKm !== null
            ? max(1, $requestedDistanceLimitKm)
            : $defaultDistance;

        if (! $this->isActive()) {
            return $requested;
        }

        return max($requested, $this->expandedRadiusKm());
    }

    public function activate(?string $trigger = null, ?int $actorUserId = null, ?int $expiresInHours = null): array
    {
        $this->authorizeAdminActor($actorUserId);

        $state = DB::transaction(function () use ($trigger, $actorUserId, $expiresInHours) {
            $now = now();

            EmergencyState::query()->lockForUpdate()->find(self::SINGLETON_STATE_ID);

            $record = EmergencyState::query()->updateOrCreate(
                ['id' => self::SINGLETON_STATE_ID],
                [
                    'is_active' => true,
                    'trigger' => $this->normalizeTrigger($trigger),
                    'triggered_by' => $actorUserId,
                    'triggered_at' => $now,
                    'expires_at' => $this->resolveExpiration($expiresInHours, $now),
                ]
            );

            return $this->stateFromRecord($record->fresh());
        });

        $this->syncCache($state);

        ActivityLog::record($actorUserId, 'emergency-broadcast-mode.activated', [
            'trigger' => $state['trigger'],
            'triggered_at' => $state['triggered_at'],
            'expires_at' => $state['expires_at'],
        ]);

        return $state;
    }

    public function deactivate(?int $actorUserId = null, string $reason = 'manual'): array
    {
        $this->authorizeAdminActor($actorUserId);

        return $this->deactivateState($actorUserId, $reason);
    }

    public function warmCacheFromDatabase(): void
    {
        if (! $this->tableAvailable()) {
            return;
        }

        try {
            if (! $this->expireExpiredState()) {
                $this->syncCache($this->stateFromRecord($this->currentStateRecord()));
            }
        } catch (Throwable $exception) {
            Log::warning('emergency-broadcast-mode.cache-warm-failed', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function expireExpiredState(): bool
    {
        if (! $this->tableAvailable()) {
            return false;
        }

        $record = $this->currentStateRecord();

        if (! $record?->is_active || $record->expires_at === null || $record->expires_at->isFuture()) {
            return false;
        }

        $this->deactivateState(null, 'expired');

        return true;
    }

    public function nextEscalationDelayMinutes(): int
    {
        if (! $this->isActive()) {
            return 5;
        }

        return max(1, (int) config('services.notifications.emergency_escalation_delay_minutes', 2));
    }

    public function maxNotificationDelayMinutesForUrgency(string $urgency): int
    {
        if (! $this->isActive()) {
            return match (strtolower(trim($urgency))) {
                'high' => 30,
                'low' => 240,
                default => 90,
            };
        }

        return match (strtolower(trim($urgency))) {
            'high' => max(1, (int) config('services.notifications.emergency_high_urgency_max_delay_minutes', 5)),
            'low' => max(1, (int) config('services.notifications.emergency_low_urgency_max_delay_minutes', 30)),
            default => max(1, (int) config('services.notifications.emergency_medium_urgency_max_delay_minutes', 15)),
        };
    }

    public function emergencyPriorityBoostFactor(): float
    {
        if (! $this->isActive()) {
            return 0.0;
        }

        return max(0.0, min(1.0, (float) config('services.notifications.emergency_priority_boost_factor', 0.15)));
    }

    public function expandedRadiusKm(): int
    {
        return max(1, (int) config('services.notifications.disaster_expanded_radius_km', 200));
    }

    /**
     * @return Collection<int, Donor>
     */
    public function eligibleDonorsForBroadcast(BloodRequest $bloodRequest, DonorFilterService $donorFilterService): Collection
    {
        $compatibleTypes = $donorFilterService->compatibleDonorTypes($bloodRequest->blood_type);

        return Donor::query()
            ->whereIn('blood_type', $compatibleTypes)
            ->where('availability', true)
            ->where(function ($q) {
                $q->whereNull('last_donation_date')
                    ->orWhereDate('last_donation_date', '<=', now()->subDays(DonorFilterService::MIN_DONATION_INTERVAL_DAYS)->toDateString());
            })
            ->orderByDesc('reliability_score')
            ->get();
    }

    public function broadcastForRequest(
        BloodRequest $bloodRequest,
        DonorFilterService $donorFilterService,
        NotificationService $notificationService,
        DonorCooldownService $cooldownService
    ): int {
        $pacingUs = max(0, (int) config('services.notifications.pacing_us', 5000));

        $eligible = $this->eligibleDonorsForBroadcast($bloodRequest, $donorFilterService);
        $sent = 0;

        foreach ($eligible as $donor) {
            if (! $cooldownService->canNotifyDonor($donor)) {
                continue;
            }

            $notificationService->sendDonorAlert(
                donor: $donor,
                bloodRequest: $bloodRequest,
                distanceKm: null
            );

            $cooldownService->recordAlert($bloodRequest, $donor, self::BROADCAST_ESCALATION_LEVEL);
            $sent++;

            if ($pacingUs > 0) {
                usleep($pacingUs);
            }
        }

        ActivityLog::record(null, 'emergency-broadcast-mode.broadcast-executed', [
            'blood_request_id' => $bloodRequest->id,
            'eligible_donors' => $eligible->count(),
            'notifications_sent' => $sent,
            'emergency_trigger' => $this->state()['trigger'],
        ]);

        return $sent;
    }

    /**
     * @return array<int, string>
     */
    private function disasterTriggers(): array
    {
        $configured = config('services.notifications.disaster_triggers', self::DEFAULT_DISASTER_TRIGGERS);

        if (! is_array($configured) || $configured === []) {
            return self::DEFAULT_DISASTER_TRIGGERS;
        }

        return collect($configured)
            ->map(fn ($trigger) => $this->normalizeTrigger(is_string($trigger) ? $trigger : null))
            ->filter(fn ($trigger) => $trigger !== null)
            ->values()
            ->all();
    }

    private function normalizeTrigger(?string $trigger): ?string
    {
        if ($trigger === null) {
            return null;
        }

        $normalized = strtolower(trim($trigger));

        return $normalized === '' ? null : $normalized;
    }

    private function deactivateState(?int $actorUserId, string $reason): array
    {
        $previousState = $this->stateFromRecord($this->currentStateRecord());

        $state = DB::transaction(function () use ($previousState) {
            EmergencyState::query()->lockForUpdate()->find(self::SINGLETON_STATE_ID);

            $record = EmergencyState::query()->updateOrCreate(
                ['id' => self::SINGLETON_STATE_ID],
                [
                    'is_active' => false,
                    'trigger' => $previousState['trigger'],
                    'triggered_by' => $previousState['triggered_by'],
                    'triggered_at' => $previousState['triggered_at'] !== null
                        ? Carbon::parse($previousState['triggered_at'])
                        : null,
                    'expires_at' => null,
                ]
            );

            return $this->stateFromRecord($record->fresh());
        });

        $this->syncCache($state);

        ActivityLog::record($actorUserId, 'emergency-broadcast-mode.deactivated', [
            'reason' => $reason,
            'trigger' => $previousState['trigger'],
            'triggered_at' => $previousState['triggered_at'],
            'deactivated_at' => now()->toDateTimeString(),
            'duration_seconds' => $previousState['active_duration_seconds'],
            'impact' => $this->impactSummary(
                $previousState['triggered_at'] !== null ? Carbon::parse($previousState['triggered_at']) : null,
                now()
            ),
        ]);

        return $state;
    }

    private function authorizeAdminActor(?int $actorUserId): void
    {
        if ($actorUserId === null) {
            return;
        }

        $actor = User::query()->find($actorUserId);

        if (! $actor || (string) $actor->role !== 'admin') {
            throw new AuthorizationException('Emergency mode changes require admin authorization.');
        }
    }

    private function currentStateRecord(): ?EmergencyState
    {
        if (! $this->tableAvailable()) {
            return null;
        }

        return EmergencyState::query()->find(self::SINGLETON_STATE_ID)
            ?? EmergencyState::query()->latest('id')->first();
    }

    /**
     * @param array<string, mixed> $state
     * @return array{enabled: bool, trigger: string|null, activated_at: string|null, activated_by: int|null, triggered_at: string|null, triggered_by: int|null, expires_at: string|null, active_duration_seconds: int}
     */
    private function normalizeState(array $state): array
    {
        $triggeredAt = isset($state['triggered_at']) && is_string($state['triggered_at'])
            ? $state['triggered_at']
            : (isset($state['activated_at']) && is_string($state['activated_at']) ? $state['activated_at'] : null);

        $triggeredBy = isset($state['triggered_by'])
            ? (int) $state['triggered_by']
            : (isset($state['activated_by']) ? (int) $state['activated_by'] : null);

        $enabled = (bool) ($state['enabled'] ?? false);
        $activeDurationSeconds = 0;

        if ($enabled && $triggeredAt !== null) {
            $activeDurationSeconds = Carbon::parse($triggeredAt)->diffInSeconds(now());
        }

        return [
            'enabled' => $enabled,
            'trigger' => isset($state['trigger']) ? (string) $state['trigger'] : null,
            'activated_at' => $triggeredAt,
            'activated_by' => $triggeredBy,
            'triggered_at' => $triggeredAt,
            'triggered_by' => $triggeredBy,
            'expires_at' => isset($state['expires_at']) && is_string($state['expires_at']) ? $state['expires_at'] : null,
            'active_duration_seconds' => $activeDurationSeconds,
        ];
    }

    /**
     * @return array{enabled: bool, trigger: string|null, activated_at: string|null, activated_by: int|null, triggered_at: string|null, triggered_by: int|null, expires_at: string|null, active_duration_seconds: int}
     */
    private function stateFromRecord(?EmergencyState $record): array
    {
        if (! $record) {
            return [
                'enabled' => false,
                'trigger' => null,
                'activated_at' => null,
                'activated_by' => null,
                'triggered_at' => null,
                'triggered_by' => null,
                'expires_at' => null,
                'active_duration_seconds' => 0,
            ];
        }

        $triggeredAt = $record->triggered_at?->toDateTimeString();

        return [
            'enabled' => (bool) $record->is_active,
            'trigger' => $record->trigger,
            'activated_at' => $triggeredAt,
            'activated_by' => $record->triggered_by,
            'triggered_at' => $triggeredAt,
            'triggered_by' => $record->triggered_by,
            'expires_at' => $record->expires_at?->toDateTimeString(),
            'active_duration_seconds' => $record->is_active && $record->triggered_at !== null
                ? $record->triggered_at->diffInSeconds(now())
                : 0,
        ];
    }

    /**
     * @param array{enabled: bool, trigger: string|null, activated_at: string|null, activated_by: int|null, triggered_at: string|null, triggered_by: int|null, expires_at: string|null, active_duration_seconds: int} $state
     */
    private function syncCache(array $state): void
    {
        Cache::forever(self::CACHE_KEY, $state);
    }

    /**
     * @param array{enabled: bool, trigger: string|null, activated_at: string|null, activated_by: int|null, triggered_at: string|null, triggered_by: int|null, expires_at: string|null, active_duration_seconds: int} $state
     */
    private function hasExpired(array $state): bool
    {
        if (! $state['enabled'] || $state['expires_at'] === null) {
            return false;
        }

        return now()->greaterThanOrEqualTo(Carbon::parse($state['expires_at']));
    }

    private function resolveExpiration(?int $expiresInHours, CarbonInterface $now): ?CarbonInterface
    {
        $resolvedHours = $expiresInHours;

        if ($resolvedHours === null) {
            $defaultHours = (int) config('services.notifications.emergency_default_expiration_hours', 0);
            $resolvedHours = $defaultHours > 0 ? $defaultHours : null;
        }

        if ($resolvedHours === null || $resolvedHours <= 0) {
            return null;
        }

        return $now->copy()->addHours($resolvedHours);
    }

    private function impactSummary(?CarbonInterface $startedAt, CarbonInterface $endedAt): array
    {
        if ($startedAt === null) {
            return [
                'requests_created' => 0,
                'donor_alerts_sent' => 0,
                'accepted_responses' => 0,
                'donations_completed' => 0,
            ];
        }

        return [
            'requests_created' => BloodRequest::query()
                ->whereBetween('created_at', [$startedAt, $endedAt])
                ->count(),
            'donor_alerts_sent' => DonorAlertLog::query()
                ->whereBetween('sent_at', [$startedAt, $endedAt])
                ->count(),
            'accepted_responses' => DonorRequestResponse::query()
                ->where('response', 'accepted')
                ->whereBetween('responded_at', [$startedAt, $endedAt])
                ->count(),
            'donations_completed' => DonationHistory::query()
                ->where('status', 'completed')
                ->whereBetween('created_at', [$startedAt, $endedAt])
                ->count(),
        ];
    }

    private function tableAvailable(): bool
    {
        try {
            return Schema::hasTable('emergency_states');
        } catch (Throwable) {
            return false;
        }
    }
}