<?php

namespace App\Jobs;

use App\Models\BloodRequest;
use App\Models\ActivityLog;
use App\Services\DonorCooldownService;
use App\Services\DonorFilterService;
use App\Services\DonorNotificationTimingService;
use App\Services\EmergencyBroadcastModeService;
use App\Services\EmergencyEscalationService;
use App\Services\MonitoringMetricsService;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendEmergencyNotificationsJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 120;

    public bool $failOnTimeout = true;

    public int $maxExceptions = 3;

    public int $uniqueFor = 600;

    public function __construct(
        public int $bloodRequestId,
        public int $escalationLevel = EmergencyEscalationService::LEVEL_CLOSEST
    ) {
        $this->onQueue('notifications');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    /**
     * @return array<int, mixed>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->uniqueId()))
                ->releaseAfter(10)
                ->expireAfter(600),
        ];
    }

    public function uniqueId(): string
    {
        return 'notifications:request:'.$this->bloodRequestId.':level:'.$this->escalationLevel;
    }

    public function handle(
        NotificationService $notificationService,
        EmergencyEscalationService $escalationService,
        DonorCooldownService $cooldownService,
        EmergencyBroadcastModeService $emergencyBroadcastModeService,
        DonorFilterService $donorFilterService,
        DonorNotificationTimingService $timingService,
        ?MonitoringMetricsService $metrics = null
    ): void
    {
        $start = microtime(true);
        $metrics ??= app(MonitoringMetricsService::class);

        Log::info('queue.job.start', [
            'job' => self::class,
            'queue' => 'notifications',
            'attempt' => $this->attempts(),
            'max_attempts' => $this->tries,
            'blood_request_id' => $this->bloodRequestId,
            'escalation_level' => $this->escalationLevel,
        ]);

        $bloodRequest = BloodRequest::query()->find($this->bloodRequestId);

        if (! $bloodRequest) {
            $metrics->recordRequestProcessing('notifications', (microtime(true) - $start) * 1000, false);

            Log::warning('queue.job.skipped', [
                'job' => self::class,
                'reason' => 'blood_request_not_found',
                'blood_request_id' => $this->bloodRequestId,
                'escalation_level' => $this->escalationLevel,
            ]);

            return;
        }

        if ((bool) data_get(Cache::get('past-match:control:'.$bloodRequest->id, []), 'notifications_paused', false)) {
            $metrics->recordRequestProcessing('notifications', (microtime(true) - $start) * 1000, true);

            ActivityLog::record(null, 'past-match.notifications-paused-skip', [
                'blood_request_id' => $bloodRequest->id,
                'escalation_level' => $this->escalationLevel,
            ]);

            Log::info('queue.job.skipped', [
                'job' => self::class,
                'reason' => 'notifications_paused',
                'blood_request_id' => $bloodRequest->id,
                'escalation_level' => $this->escalationLevel,
            ]);

            return;
        }

        if (! $escalationService->shouldEscalate($bloodRequest)) {
            $metrics->recordRequestProcessing('notifications', (microtime(true) - $start) * 1000, true);

            Log::info('queue.job.skipped', [
                'job' => self::class,
                'reason' => 'escalation_not_required',
                'blood_request_id' => $this->bloodRequestId,
                'escalation_level' => $this->escalationLevel,
            ]);

            return;
        }

        try {
            if ($emergencyBroadcastModeService->isActive()) {
                $sent = $emergencyBroadcastModeService->broadcastForRequest(
                    bloodRequest: $bloodRequest,
                    donorFilterService: $donorFilterService,
                    notificationService: $notificationService,
                    cooldownService: $cooldownService
                );

                $metrics->recordRequestProcessing('notifications', (microtime(true) - $start) * 1000, true);

                Log::info('queue.job.success', [
                    'job' => self::class,
                    'queue' => 'notifications',
                    'attempt' => $this->attempts(),
                    'blood_request_id' => $this->bloodRequestId,
                    'escalation_level' => $this->escalationLevel,
                    'mode' => 'broadcast',
                    'notifications_sent' => $sent,
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return;
            }

            $maxBurst = max(1, (int) config('services.notifications.max_burst', 20));
            $pacingUs = max(0, (int) config('services.notifications.pacing_us', 5000));
            $candidates = $escalationService
                ->recipientsForLevel($bloodRequest, $this->escalationLevel)
                ->take($maxBurst)
                ->values();

            $scheduledNow = now();
            $maxDelayMinutes = $emergencyBroadcastModeService
                ->maxNotificationDelayMinutesForUrgency((string) $bloodRequest->urgency_level);
            $immediateSent = 0;
            $timedScheduled = 0;

            foreach ($candidates as $candidate) {
                $donor = $candidate['donor'];

                if (! $cooldownService->canNotifyDonor($donor)) {
                    continue;
                }

                if ($timingService->isPredictedUnavailableNow($donor, $scheduledNow)) {
                    continue;
                }

                $timingPlan = $timingService->planForDonor($donor, $scheduledNow, $maxDelayMinutes);
                $sendAt = $timingPlan['send_at'];

                if ($sendAt->gt($scheduledNow->copy()->addMinute())) {
                    SendTimedDonorAlertJob::dispatch(
                        bloodRequestId: $bloodRequest->id,
                        donorId: $donor->id,
                        escalationLevel: $this->escalationLevel,
                        distanceKm: $candidate['distance_km']
                    )
                        ->delay($sendAt)
                        ->onQueue('notifications');

                    $timedScheduled++;
                    continue;
                }

                $notificationService->sendDonorAlert(
                    donor: $donor,
                    bloodRequest: $bloodRequest,
                    distanceKm: $candidate['distance_km']
                );

                $cooldownService->recordAlert($bloodRequest, $donor, $this->escalationLevel);
                $immediateSent++;

                if ($pacingUs > 0) {
                    usleep($pacingUs);
                }
            }

            if ($this->escalationLevel < EmergencyEscalationService::LEVEL_ALL_COMPATIBLE
                && $escalationService->shouldEscalate($bloodRequest->fresh())
            ) {
                self::dispatch(
                    bloodRequestId: $bloodRequest->id,
                    escalationLevel: $this->escalationLevel + 1
                )
                    ->delay(now()->addMinutes($emergencyBroadcastModeService->nextEscalationDelayMinutes()))
                    ->onQueue('notifications');
            }

            $metrics->recordRequestProcessing('notifications', (microtime(true) - $start) * 1000, true);

            Log::info('queue.job.success', [
                'job' => self::class,
                'queue' => 'notifications',
                'attempt' => $this->attempts(),
                'blood_request_id' => $this->bloodRequestId,
                'escalation_level' => $this->escalationLevel,
                'immediate_sent' => $immediateSent,
                'timed_scheduled' => $timedScheduled,
                'candidate_count' => $candidates->count(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
        } catch (Throwable $exception) {
            $metrics->recordRequestProcessing('notifications', (microtime(true) - $start) * 1000, false);

            Log::error('queue.job.error', [
                'job' => self::class,
                'queue' => 'notifications',
                'attempt' => $this->attempts(),
                'blood_request_id' => $this->bloodRequestId,
                'escalation_level' => $this->escalationLevel,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        app(MonitoringMetricsService::class)
            ->recordRequestProcessing('notifications', 0, false);

        Log::critical('queue.job.failed', [
            'job' => self::class,
            'queue' => 'notifications',
            'blood_request_id' => $this->bloodRequestId,
            'escalation_level' => $this->escalationLevel,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);
    }
}
