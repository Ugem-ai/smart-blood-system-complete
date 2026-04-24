<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\BloodRequest;
use App\Models\DonationHistory;
use App\Models\DonorAlertLog;
use App\Models\Donor;
use App\Models\DonorRequestResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class MonitoringMetricsService
{
    public function recordApiResponse(string $method, string $path, int $status, float $durationMs): void
    {
        $method = strtoupper($method);
        $path = $this->normalizePath($path);
        $bucket = "monitor:api:{$method}:{$path}:{$status}";

        $this->increment("{$bucket}:count");
        $this->addFloat("{$bucket}:duration_sum_ms", $durationMs);

        $this->increment('monitor:api:total_requests');
        $this->addFloat('monitor:api:duration_sum_ms', $durationMs);
    }

    public function recordRequestProcessing(string $process, float $durationMs, bool $success): void
    {
        $process = strtolower($process);
        $status = $success ? 'success' : 'failure';

        $this->increment("monitor:process:{$process}:{$status}:count");
        $this->addFloat("monitor:process:{$process}:duration_sum_ms", $durationMs);
    }

    public function recordNotificationResult(string $channel, bool $success): void
    {
        $channel = strtolower($channel);
        $status = $success ? 'success' : 'failure';

        $this->increment("monitor:notification:{$channel}:{$status}");
    }

    public function recordNotificationDelivery(bool $success, float $durationMs): void
    {
        $status = $success ? 'success' : 'failure';

        $this->increment("monitor:delivery:{$status}");
        $this->increment('monitor:delivery:total');
        $this->addFloat('monitor:delivery:duration_sum_ms', $durationMs);
    }

    public function prometheusPayload(): string
    {
        $totalRequests = (int) Cache::get('monitor:api:total_requests', 0);
        $totalDuration = (float) Cache::get('monitor:api:duration_sum_ms', 0.0);

        $matchingSuccess = (int) Cache::get('monitor:process:matching:success:count', 0);
        $matchingFailure = (int) Cache::get('monitor:process:matching:failure:count', 0);
        $matchingDuration = (float) Cache::get('monitor:process:matching:duration_sum_ms', 0.0);

        $notificationSuccess = (int) Cache::get('monitor:process:notifications:success:count', 0);
        $notificationFailure = (int) Cache::get('monitor:process:notifications:failure:count', 0);
        $notificationDuration = (float) Cache::get('monitor:process:notifications:duration_sum_ms', 0.0);

        $timedNotificationSuccess = (int) Cache::get('monitor:process:timed_notifications:success:count', 0);
        $timedNotificationFailure = (int) Cache::get('monitor:process:timed_notifications:failure:count', 0);
        $timedNotificationDuration = (float) Cache::get('monitor:process:timed_notifications:duration_sum_ms', 0.0);

        $notifPushSuccess = (int) Cache::get('monitor:notification:push:success', 0);
        $notifPushFailure = (int) Cache::get('monitor:notification:push:failure', 0);
        $notifSmsSuccess = (int) Cache::get('monitor:notification:sms:success', 0);
        $notifSmsFailure = (int) Cache::get('monitor:notification:sms:failure', 0);
        $deliverySuccess = (int) Cache::get('monitor:delivery:success', 0);
        $deliveryFailure = (int) Cache::get('monitor:delivery:failure', 0);
        $deliveryTotal = (int) Cache::get('monitor:delivery:total', 0);
        $deliveryDurationSum = (float) Cache::get('monitor:delivery:duration_sum_ms', 0.0);
        $deliverySuccessRate = $deliveryTotal > 0
            ? round(($deliverySuccess / $deliveryTotal) * 100, 2)
            : 0.0;
        $deliveryFailureRate = $deliveryTotal > 0
            ? round(($deliveryFailure / $deliveryTotal) * 100, 2)
            : 0.0;
        $deliveryAvgMs = $deliveryTotal > 0
            ? round($deliveryDurationSum / $deliveryTotal, 2)
            : 0.0;

        $activeDonors = Donor::query()->where('availability', true)->count();
        $dailyRequests = BloodRequest::query()->whereDate('created_at', today())->count();
        $successfulDonations = DonationHistory::query()->where('status', 'completed')->count();
        $avgResponseMinutes = $this->averageResponseMinutes();
        $liveStatuses = ['pending', 'matching', 'open'];
        $liveBloodRequests = BloodRequest::query()->whereIn('status', $liveStatuses)->count();
        $activeDonorAlerts = DonorAlertLog::query()->where('sent_at', '>=', now()->subMinutes(30))->count();
        $acceptedRequests = DonorRequestResponse::query()->where('response', 'accepted')->count();
        $donationsCompleted = DonationHistory::query()->where('status', 'completed')->count();
        $emergencyState = app(EmergencyBroadcastModeService::class)->state();
        $emergencyActivationCount = ActivityLog::query()
            ->where('action', 'emergency-broadcast-mode.activated')
            ->count();
        $emergencyActive = (bool) ($emergencyState['enabled'] ?? false);
        $emergencyActiveDurationSeconds = (int) ($emergencyState['active_duration_seconds'] ?? 0);

        $lines = [
            '# HELP smartblood_api_requests_total Total API requests observed',
            '# TYPE smartblood_api_requests_total counter',
            "smartblood_api_requests_total {$totalRequests}",
            '# HELP smartblood_api_response_duration_ms_sum Sum of API response times in ms',
            '# TYPE smartblood_api_response_duration_ms_sum counter',
            "smartblood_api_response_duration_ms_sum {$totalDuration}",
            '# HELP smartblood_matching_jobs_total Matching job outcomes',
            '# TYPE smartblood_matching_jobs_total counter',
            "smartblood_matching_jobs_total{status=\"success\"} {$matchingSuccess}",
            "smartblood_matching_jobs_total{status=\"failure\"} {$matchingFailure}",
            '# HELP smartblood_matching_job_duration_ms_sum Sum of matching job durations in ms',
            '# TYPE smartblood_matching_job_duration_ms_sum counter',
            "smartblood_matching_job_duration_ms_sum {$matchingDuration}",
            '# HELP smartblood_notification_jobs_total Notification job outcomes',
            '# TYPE smartblood_notification_jobs_total counter',
            "smartblood_notification_jobs_total{type=\"batch\",status=\"success\"} {$notificationSuccess}",
            "smartblood_notification_jobs_total{type=\"batch\",status=\"failure\"} {$notificationFailure}",
            "smartblood_notification_jobs_total{type=\"timed\",status=\"success\"} {$timedNotificationSuccess}",
            "smartblood_notification_jobs_total{type=\"timed\",status=\"failure\"} {$timedNotificationFailure}",
            '# HELP smartblood_notification_job_duration_ms_sum Sum of notification job durations in ms',
            '# TYPE smartblood_notification_job_duration_ms_sum counter',
            "smartblood_notification_job_duration_ms_sum{type=\"batch\"} {$notificationDuration}",
            "smartblood_notification_job_duration_ms_sum{type=\"timed\"} {$timedNotificationDuration}",
            '# HELP smartblood_notifications_total Notification outcomes by channel',
            '# TYPE smartblood_notifications_total counter',
            "smartblood_notifications_total{channel=\"push\",status=\"success\"} {$notifPushSuccess}",
            "smartblood_notifications_total{channel=\"push\",status=\"failure\"} {$notifPushFailure}",
            "smartblood_notifications_total{channel=\"sms\",status=\"success\"} {$notifSmsSuccess}",
            "smartblood_notifications_total{channel=\"sms\",status=\"failure\"} {$notifSmsFailure}",
            '# HELP smartblood_notification_delivery_success_rate_percent Notification delivery success rate percentage',
            '# TYPE smartblood_notification_delivery_success_rate_percent gauge',
            "smartblood_notification_delivery_success_rate_percent {$deliverySuccessRate}",
            '# HELP smartblood_notification_delivery_failure_rate_percent Notification delivery failure rate percentage',
            '# TYPE smartblood_notification_delivery_failure_rate_percent gauge',
            "smartblood_notification_delivery_failure_rate_percent {$deliveryFailureRate}",
            '# HELP smartblood_notification_delivery_avg_duration_ms Average notification delivery duration in milliseconds',
            '# TYPE smartblood_notification_delivery_avg_duration_ms gauge',
            "smartblood_notification_delivery_avg_duration_ms {$deliveryAvgMs}",
            '# HELP smartblood_active_donors Number of active donors available for matching',
            '# TYPE smartblood_active_donors gauge',
            "smartblood_active_donors {$activeDonors}",
            '# HELP smartblood_daily_requests Number of blood requests created today',
            '# TYPE smartblood_daily_requests gauge',
            "smartblood_daily_requests {$dailyRequests}",
            '# HELP smartblood_successful_donations Total successful completed donations',
            '# TYPE smartblood_successful_donations gauge',
            "smartblood_successful_donations {$successfulDonations}",
            '# HELP smartblood_average_response_time_minutes Average donor response time in minutes',
            '# TYPE smartblood_average_response_time_minutes gauge',
            "smartblood_average_response_time_minutes {$avgResponseMinutes}",
            '# HELP smartblood_emergency_live_blood_requests Current live blood requests in pending/matching/open state',
            '# TYPE smartblood_emergency_live_blood_requests gauge',
            "smartblood_emergency_live_blood_requests {$liveBloodRequests}",
            '# HELP smartblood_emergency_active_donor_alerts Active donor alerts in the last 30 minutes',
            '# TYPE smartblood_emergency_active_donor_alerts gauge',
            "smartblood_emergency_active_donor_alerts {$activeDonorAlerts}",
            '# HELP smartblood_emergency_accepted_requests Total accepted donor responses',
            '# TYPE smartblood_emergency_accepted_requests gauge',
            "smartblood_emergency_accepted_requests {$acceptedRequests}",
            '# HELP smartblood_emergency_donations_completed Total completed donations',
            '# TYPE smartblood_emergency_donations_completed gauge',
            "smartblood_emergency_donations_completed {$donationsCompleted}",
            '# HELP smartblood_emergency_mode_active Whether emergency mode is currently active',
            '# TYPE smartblood_emergency_mode_active gauge',
            'smartblood_emergency_mode_active '.($emergencyActive ? '1' : '0'),
            '# HELP smartblood_emergency_mode_activation_count_total Total emergency mode activations recorded',
            '# TYPE smartblood_emergency_mode_activation_count_total counter',
            "smartblood_emergency_mode_activation_count_total {$emergencyActivationCount}",
            '# HELP smartblood_emergency_mode_active_duration_seconds Current emergency mode active duration in seconds',
            '# TYPE smartblood_emergency_mode_active_duration_seconds gauge',
            "smartblood_emergency_mode_active_duration_seconds {$emergencyActiveDurationSeconds}",
        ];

        return implode("\n", $lines)."\n";
    }

    /**
     * @return array<string, mixed>
     */
    public function healthSnapshot(): array
    {
        $dbOk = true;
        $redisOk = true;

        try {
            DB::select('SELECT 1');
        } catch (Throwable) {
            $dbOk = false;
        }

        try {
            Redis::connection()->ping();
        } catch (Throwable) {
            $redisOk = false;
        }

        return [
            'status' => ($dbOk && $redisOk) ? 'ok' : 'degraded',
            'services' => [
                'database' => $dbOk ? 'up' : 'down',
                'redis' => $redisOk ? 'up' : 'down',
                'queue_connection' => config('queue.default'),
            ],
            'emergency_mode' => app(EmergencyBroadcastModeService::class)->state(),
        ];
    }

    protected function normalizePath(string $path): string
    {
        $path = trim($path, '/');
        $path = preg_replace('/\d+/', ':id', $path ?? '') ?? '';

        return $path === '' ? 'root' : $path;
    }

    protected function averageResponseMinutes(): float
    {
        $base = DonorRequestResponse::query()
            ->join('blood_requests', 'blood_requests.id', '=', 'donor_request_responses.blood_request_id')
            ->whereNotNull('donor_request_responses.responded_at');

        $driver = DB::connection()->getDriverName();

        $raw = $driver === 'sqlite'
            ? $base
                ->selectRaw('AVG((julianday(donor_request_responses.responded_at) - julianday(blood_requests.created_at)) * 24 * 60) as avg_minutes')
                ->value('avg_minutes')
            : $base
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, blood_requests.created_at, donor_request_responses.responded_at)) as avg_minutes')
                ->value('avg_minutes');

        return round((float) ($raw ?? 0), 2);
    }

    protected function increment(string $key): void
    {
        if (! Cache::has($key)) {
            Cache::forever($key, 0);
        }

        Cache::increment($key);
    }

    protected function addFloat(string $key, float $value): void
    {
        $current = (float) Cache::get($key, 0.0);
        Cache::forever($key, round($current + $value, 4));
    }
}
