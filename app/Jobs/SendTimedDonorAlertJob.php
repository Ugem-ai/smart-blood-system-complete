<?php

namespace App\Jobs;

use App\Models\BloodRequest;
use App\Models\ActivityLog;
use App\Models\Donor;
use App\Services\DonorCooldownService;
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

class SendTimedDonorAlertJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 90;

    public bool $failOnTimeout = true;

    public int $maxExceptions = 3;

    public int $uniqueFor = 900;

    public function __construct(
        public int $bloodRequestId,
        public int $donorId,
        public int $escalationLevel,
        public ?float $distanceKm = null
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
                ->expireAfter(900),
        ];
    }

    public function uniqueId(): string
    {
        return implode(':', [
            'timed-alert',
            'request',
            $this->bloodRequestId,
            'donor',
            $this->donorId,
            'level',
            $this->escalationLevel,
        ]);
    }

    public function handle(
        NotificationService $notificationService,
        DonorCooldownService $cooldownService,
        EmergencyEscalationService $escalationService,
        ?MonitoringMetricsService $metrics = null
    ): void {
        $start = microtime(true);
        $metrics ??= app(MonitoringMetricsService::class);

        Log::info('queue.job.start', [
            'job' => self::class,
            'queue' => 'notifications',
            'attempt' => $this->attempts(),
            'max_attempts' => $this->tries,
            'blood_request_id' => $this->bloodRequestId,
            'donor_id' => $this->donorId,
            'escalation_level' => $this->escalationLevel,
            'distance_km' => $this->distanceKm,
        ]);

        $bloodRequest = BloodRequest::query()->find($this->bloodRequestId);
        $donor = Donor::query()->find($this->donorId);

        if (! $bloodRequest || ! $donor) {
            $metrics->recordRequestProcessing('timed_notifications', (microtime(true) - $start) * 1000, false);

            Log::warning('queue.job.skipped', [
                'job' => self::class,
                'reason' => 'request_or_donor_not_found',
                'blood_request_id' => $this->bloodRequestId,
                'donor_id' => $this->donorId,
            ]);

            return;
        }

        if ((bool) data_get(Cache::get('past-match:control:'.$bloodRequest->id, []), 'notifications_paused', false)) {
            $metrics->recordRequestProcessing('timed_notifications', (microtime(true) - $start) * 1000, true);

            ActivityLog::record(null, 'past-match.notifications-paused-skip', [
                'blood_request_id' => $bloodRequest->id,
                'donor_id' => $this->donorId,
                'escalation_level' => $this->escalationLevel,
            ]);

            Log::info('queue.job.skipped', [
                'job' => self::class,
                'reason' => 'notifications_paused',
                'blood_request_id' => $bloodRequest->id,
                'donor_id' => $this->donorId,
                'escalation_level' => $this->escalationLevel,
            ]);

            return;
        }

        if (! $escalationService->shouldEscalate($bloodRequest)) {
            $metrics->recordRequestProcessing('timed_notifications', (microtime(true) - $start) * 1000, true);

            Log::info('queue.job.skipped', [
                'job' => self::class,
                'reason' => 'escalation_not_required',
                'blood_request_id' => $this->bloodRequestId,
                'donor_id' => $this->donorId,
            ]);

            return;
        }

        if (! $cooldownService->canNotifyDonor($donor)) {
            $metrics->recordRequestProcessing('timed_notifications', (microtime(true) - $start) * 1000, true);

            Log::info('queue.job.skipped', [
                'job' => self::class,
                'reason' => 'cooldown_active',
                'blood_request_id' => $this->bloodRequestId,
                'donor_id' => $this->donorId,
            ]);

            return;
        }

        try {
            $notificationService->sendDonorAlert(
                donor: $donor,
                bloodRequest: $bloodRequest,
                distanceKm: $this->distanceKm
            );

            $cooldownService->recordAlert($bloodRequest, $donor, $this->escalationLevel);

            $metrics->recordRequestProcessing('timed_notifications', (microtime(true) - $start) * 1000, true);

            Log::info('queue.job.success', [
                'job' => self::class,
                'queue' => 'notifications',
                'attempt' => $this->attempts(),
                'blood_request_id' => $this->bloodRequestId,
                'donor_id' => $this->donorId,
                'escalation_level' => $this->escalationLevel,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
        } catch (Throwable $exception) {
            $metrics->recordRequestProcessing('timed_notifications', (microtime(true) - $start) * 1000, false);

            Log::error('queue.job.error', [
                'job' => self::class,
                'queue' => 'notifications',
                'attempt' => $this->attempts(),
                'blood_request_id' => $this->bloodRequestId,
                'donor_id' => $this->donorId,
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
            ->recordRequestProcessing('timed_notifications', 0, false);

        Log::critical('queue.job.failed', [
            'job' => self::class,
            'queue' => 'notifications',
            'blood_request_id' => $this->bloodRequestId,
            'donor_id' => $this->donorId,
            'escalation_level' => $this->escalationLevel,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);
    }
}
