<?php

namespace App\Jobs;

use App\Algorithms\PASTMatch;
use App\Models\ActivityLog;
use App\Models\BloodRequest;
use App\Models\RequestMatch;
use App\Services\BloodRequestService;
use App\Services\DonorFilterService;
use App\Services\MonitoringMetricsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessBloodRequestMatchingJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 120;

    public bool $failOnTimeout = true;

    public int $maxExceptions = 3;

    public int $uniqueFor = 300;

    public function __construct(
        public int $bloodRequestId,
        public int $actorUserId,
        public ?int $distanceLimitKm = null
    ) {
        $this->onQueue('matching');
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
            (new WithoutOverlapping('matching:request:'.$this->bloodRequestId))
                ->releaseAfter(10)
                ->expireAfter(300),
        ];
    }

    public function uniqueId(): string
    {
        return 'matching:request:'.$this->bloodRequestId;
    }

    public function handle(DonorFilterService $donorFilterService, PASTMatch $pastMatch, MonitoringMetricsService $metrics, ?BloodRequestService $bloodRequestService = null): void
    {
        $start = microtime(true);

        Log::info('queue.job.start', [
            'job' => self::class,
            'queue' => 'matching',
            'attempt' => $this->attempts(),
            'max_attempts' => $this->tries,
            'blood_request_id' => $this->bloodRequestId,
            'actor_user_id' => $this->actorUserId,
            'distance_limit_km' => $this->distanceLimitKm,
        ]);

        $bloodRequest = BloodRequest::query()->find($this->bloodRequestId);

        if (! $bloodRequest) {
            $metrics->recordRequestProcessing('matching', (microtime(true) - $start) * 1000, false);

            Log::warning('queue.job.skipped', [
                'job' => self::class,
                'reason' => 'blood_request_not_found',
                'blood_request_id' => $this->bloodRequestId,
            ]);

            return;
        }

        try {
            $filteredDonors = $donorFilterService->filterForRequest(
                requestedBloodType: $bloodRequest->blood_type,
                requestLatitude: $bloodRequest->latitude !== null ? (float) $bloodRequest->latitude : null,
                requestLongitude: $bloodRequest->longitude !== null ? (float) $bloodRequest->longitude : null,
                distanceLimitKm: $this->distanceLimitKm ?? DonorFilterService::DEFAULT_DISTANCE_LIMIT_KM,
                requestCity: $bloodRequest->city,
            );

            $topMatches = $pastMatch->rankDonors($filteredDonors, [
                'urgency_level' => $bloodRequest->urgency_level,
            ])->take(10)->values();

            foreach ($topMatches as $index => $match) {
                RequestMatch::query()->updateOrCreate(
                    [
                        'request_id' => $bloodRequest->id,
                        'donor_id' => $match['donor']->id,
                    ],
                    [
                        'blood_request_id' => $bloodRequest->id,
                        'score' => $match['score'],
                        'response_status' => 'pending',
                        'rank' => $index + 1,
                    ]
                );
            }

            $bloodRequest->update([
                'matched_donors' => $topMatches->map(fn (array $match) => $match['donor']->name)->values()->all(),
                'matched_donors_count' => $topMatches->count(),
                'status' => $topMatches->isNotEmpty() ? 'matching' : 'pending',
            ]);

            ($bloodRequestService ?? app(BloodRequestService::class))->syncTrackingCounts($bloodRequest->fresh());

            ActivityLog::record($this->actorUserId, 'blood-request.matching-processed', [
                'blood_request_id' => $bloodRequest->id,
                'distance_limit_km' => $this->distanceLimitKm ?? DonorFilterService::DEFAULT_DISTANCE_LIMIT_KM,
                'matches_found' => $topMatches->count(),
            ]);

            if ($topMatches->isNotEmpty()) {
                SendEmergencyNotificationsJob::dispatch(
                    bloodRequestId: $bloodRequest->id
                )->onQueue('notifications');
            }

            $metrics->recordRequestProcessing('matching', (microtime(true) - $start) * 1000, true);

            Log::info('queue.job.success', [
                'job' => self::class,
                'queue' => 'matching',
                'attempt' => $this->attempts(),
                'blood_request_id' => $bloodRequest->id,
                'matches_found' => $topMatches->count(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
        } catch (Throwable $e) {
            $metrics->recordRequestProcessing('matching', (microtime(true) - $start) * 1000, false);

            Log::error('queue.job.error', [
                'job' => self::class,
                'queue' => 'matching',
                'attempt' => $this->attempts(),
                'blood_request_id' => $this->bloodRequestId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        app(MonitoringMetricsService::class)
            ->recordRequestProcessing('matching', 0, false);

        ActivityLog::record($this->actorUserId, 'blood-request.matching-failed', [
            'blood_request_id' => $this->bloodRequestId,
            'distance_limit_km' => $this->distanceLimitKm ?? DonorFilterService::DEFAULT_DISTANCE_LIMIT_KM,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);

        BloodRequest::query()
            ->where('id', $this->bloodRequestId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->update(['status' => 'pending']);

        Log::critical('queue.job.failed', [
            'job' => self::class,
            'queue' => 'matching',
            'blood_request_id' => $this->bloodRequestId,
            'actor_user_id' => $this->actorUserId,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);
    }
}
