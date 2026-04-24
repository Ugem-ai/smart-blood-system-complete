<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\BloodRequest;
use App\Models\DeviceToken;
use App\Models\Donor;
use App\Models\Hospital;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class NotificationService
{
    private const TOKEN_ERROR_INVALID = 'InvalidRegistration';

    private const TOKEN_ERROR_NOT_REGISTERED = 'NotRegistered';

    private const TOKEN_ERROR_MISMATCH = 'MismatchSenderId';

    public function sendDonorAlert(Donor $donor, BloodRequest $bloodRequest, ?float $distanceKm = null): void
    {
        $cooldownService = app(DonorCooldownService::class);

        if (! $cooldownService->canNotifyDonor($donor)) {
            ActivityLog::record(null, 'notification.throttled.cooldown', [
                'donor_id' => $donor->id,
                'blood_request_id' => $bloodRequest->id,
            ]);

            return;
        }

        $title = 'Emergency Blood Request';
        $message = sprintf(
            "Blood Type: %s\nHospital: %s\nDistance: %s\nAccept / Decline",
            $bloodRequest->blood_type,
            $bloodRequest->hospital_name,
            $distanceKm !== null ? round($distanceKm, 2).'km' : 'N/A'
        );

        $payload = [
            'type' => 'emergency_blood_request',
            'blood_request_id' => $bloodRequest->id,
            'donor_id' => $donor->id,
            'action_accept' => '/api/donor/requests/'.$bloodRequest->id.'/accept',
            'action_decline' => '/api/donor/requests/'.$bloodRequest->id.'/decline',
        ];

        $this->sendWithFallback(
            user: $donor->user,
            smsTarget: $donor->phone ?? $donor->contact_number,
            type: 'emergency_blood_request',
            title: $title,
            message: $message,
            data: $payload
        );
    }

    public function sendRequestReminder(Donor $donor, BloodRequest $bloodRequest): void
    {
        $title = 'Blood Request Reminder';
        $message = sprintf(
            'Reminder: Please respond to request #%d for blood type %s.',
            $bloodRequest->id,
            $bloodRequest->blood_type
        );

        $this->sendWithFallback(
            user: $donor->user,
            smsTarget: $donor->phone ?? $donor->contact_number,
            type: 'request_reminder',
            title: $title,
            message: $message,
            data: [
            'type' => 'request_reminder',
            'blood_request_id' => $bloodRequest->id,
            'donor_id' => $donor->id,
            ]
        );
    }

    public function sendDonationConfirmation(Donor $donor, BloodRequest $bloodRequest): void
    {
        $title = 'Donation Confirmation';
        $message = sprintf(
            'Thank you. Request #%d has been confirmed completed by %s.',
            $bloodRequest->id,
            $bloodRequest->hospital_name
        );

        $this->sendWithFallback(
            user: $donor->user,
            smsTarget: $donor->phone ?? $donor->contact_number,
            type: 'donation_confirmation',
            title: $title,
            message: $message,
            data: [
            'type' => 'donation_confirmation',
            'blood_request_id' => $bloodRequest->id,
            'donor_id' => $donor->id,
            ]
        );
    }

    public function sendCustomDonorMessage(Donor $donor, BloodRequest $bloodRequest, string $message, string $title = 'Manual Admin Message'): void
    {
        $this->sendWithFallback(
            user: $donor->user,
            smsTarget: $donor->phone ?? $donor->contact_number,
            type: 'manual_admin_message',
            title: $title,
            message: $message,
            data: [
                'type' => 'manual_admin_message',
                'blood_request_id' => $bloodRequest->id,
                'donor_id' => $donor->id,
            ]
        );
    }

    public function notificationHealth(): array
    {
        $pushConfigured = trim((string) config('services.fcm.server_key', '')) !== '';
        $smsConfigured = trim((string) config('services.twilio.sid', '')) !== ''
            && trim((string) config('services.twilio.token', '')) !== ''
            && trim((string) config('services.twilio.from', '')) !== '';

        $warnings = [];

        if (! $pushConfigured) {
            $warnings[] = 'Push notifications are not configured.';
        }

        if (! $smsConfigured) {
            $warnings[] = 'SMS notifications are not configured.';
        }

        if (! $pushConfigured && ! $smsConfigured) {
            $warnings[] = 'No notification transport is configured; donor alerts cannot be delivered.';
        }

        return [
            'ready' => $pushConfigured || $smsConfigured,
            'push_configured' => $pushConfigured,
            'sms_configured' => $smsConfigured,
            'warnings' => $warnings,
            'summary' => $pushConfigured || $smsConfigured
                ? 'Notification transport available.'
                : 'Notification transport is missing.',
        ];
    }

    public function sendHospitalResponseUpdate(Hospital $hospital, BloodRequest $bloodRequest, Donor $donor, string $response): void
    {
        $title = 'Donor Response Update';
        $message = sprintf(
            'Donor %s has %s request #%d for blood type %s.',
            $donor->name,
            $response,
            $bloodRequest->id,
            $bloodRequest->blood_type
        );

        $this->sendWithFallback(
            user: $hospital->user,
            smsTarget: $hospital->contact_number,
            type: 'hospital_donor_response',
            title: $title,
            message: $message,
            data: [
            'type' => 'hospital_donor_response',
            'blood_request_id' => $bloodRequest->id,
            'hospital_id' => $hospital->id,
            'donor_id' => $donor->id,
            'response' => $response,
            ]
        );
    }

    public function sendPushNotification(User $user, string $type, string $title, string $message, array $data = []): bool
    {
        $metrics = app(MonitoringMetricsService::class);
        $serverKey = (string) config('services.fcm.server_key');
        $endpoint = (string) config('services.fcm.endpoint', 'https://fcm.googleapis.com/fcm/send');
        $batchSize = max(1, (int) config('services.notifications.push_batch_size', 100));
        $batchPacingUs = max(0, (int) config('services.notifications.push_batch_pacing_us', 100000));
        $tokens = DeviceToken::query()
            ->where('user_id', $user->id)
            ->pluck('token')
            ->filter()
            ->values();

        if ($tokens->isEmpty()) {
            if ($serverKey !== '') {
                $start = microtime(true);

                try {
                    $response = Http::withToken($serverKey)
                        ->acceptJson()
                        ->post($endpoint, [
                            'to' => 'unregistered-device-token',
                            'notification' => [
                                'title' => $title,
                                'body' => $message,
                            ],
                            'data' => $data,
                        ]);

                    $this->recordDelivery(
                        userId: $user->id,
                        type: $type,
                        channel: 'push',
                        status: 'failed',
                        response: [
                            'title' => $title,
                            'message' => $message,
                            'payload' => $data,
                            'reason' => 'no_device_tokens',
                            'http_status' => $response->status(),
                            'response' => $response->json(),
                        ],
                        durationMs: (microtime(true) - $start) * 1000
                    );
                } catch (Throwable $exception) {
                    $this->recordDelivery(
                        userId: $user->id,
                        type: $type,
                        channel: 'push',
                        status: 'failed',
                        response: [
                            'title' => $title,
                            'message' => $message,
                            'payload' => $data,
                            'reason' => 'no_device_tokens',
                            'exception' => $exception->getMessage(),
                        ],
                        durationMs: (microtime(true) - $start) * 1000
                    );
                }
            } else {
                $this->recordDelivery(
                    userId: $user->id,
                    type: $type,
                    channel: 'push',
                    status: 'failed',
                    response: [
                        'title' => $title,
                        'message' => $message,
                        'payload' => $data,
                        'reason' => 'no_device_tokens',
                    ],
                    durationMs: 0
                );
            }

            $metrics->recordNotificationResult('push', false);

            return false;
        }

        if ($serverKey === '') {
            $this->recordDelivery(
                userId: $user->id,
                type: $type,
                channel: 'push',
                status: 'failed',
                response: [
                    'title' => $title,
                    'message' => $message,
                    'payload' => $data,
                    'reason' => 'fcm_not_configured',
                ],
                durationMs: 0
            );

            Log::info('FCM server key not configured. Push notification skipped.', [
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $data,
            ]);

            $metrics->recordNotificationResult('push', false);

            return false;
        }

        try {
            $allSuccessful = true;

            foreach ($tokens->chunk($batchSize) as $chunk) {
                $start = microtime(true);
                $chunkTokens = $chunk->values()->all();

                $response = Http::withToken($serverKey)
                    ->acceptJson()
                    ->post($endpoint, [
                        'registration_ids' => $chunkTokens,
                        'notification' => [
                            'title' => $title,
                            'body' => $message,
                        ],
                        'data' => $data,
                    ]);

                $durationMs = (microtime(true) - $start) * 1000;

                $payload = [
                    'title' => $title,
                    'message' => $message,
                    'payload' => $data,
                    'http_status' => $response->status(),
                    'response' => $response->json(),
                    'token_batch_size' => count($chunkTokens),
                ];

                $batchSuccessful = $response->successful()
                    && ((int) data_get($response->json(), 'failure', 0) === 0);

                $this->recordDelivery(
                    userId: $user->id,
                    type: $type,
                    channel: 'push',
                    status: $batchSuccessful ? 'sent' : 'failed',
                    response: $payload,
                    durationMs: $durationMs
                );

                $metrics->recordNotificationResult('push', $batchSuccessful);

                if (! $batchSuccessful) {
                    $allSuccessful = false;
                }

                $this->cleanupInvalidTokens($chunkTokens, (array) data_get($response->json(), 'results', []));

                if ($batchPacingUs > 0) {
                    usleep($batchPacingUs);
                }
            }

            DeviceToken::query()
                ->where('user_id', $user->id)
                ->whereIn('token', $tokens->all())
                ->update(['last_used_at' => now()]);

            return $allSuccessful;
        } catch (Throwable $exception) {
            $this->recordDelivery(
                userId: $user->id,
                type: $type,
                channel: 'push',
                status: 'failed',
                response: [
                    'title' => $title,
                    'message' => $message,
                    'payload' => $data,
                    'exception' => $exception->getMessage(),
                ],
                durationMs: 0
            );

            $metrics->recordNotificationResult('push', false);

            Log::error('notification.push.exception', [
                'user_id' => $user->id,
                'type' => $type,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function sendSms(?int $userId, string $type, ?string $to, string $message, array $meta = []): bool
    {
        $metrics = app(MonitoringMetricsService::class);
        $retryAttempts = max(1, (int) config('services.notifications.sms_retry_attempts', 3));
        $retryDelayMs = max(1, (int) config('services.notifications.sms_retry_delay_ms', 800));

        if (! $to) {
            $this->recordDelivery(
                userId: $userId,
                type: $type,
                channel: 'sms',
                status: 'failed',
                response: array_merge($meta, ['reason' => 'missing_recipient']),
                durationMs: 0
            );

            $metrics->recordNotificationResult('sms', false);

            return false;
        }

        $sid = (string) config('services.twilio.sid');
        $token = (string) config('services.twilio.token');
        $from = (string) config('services.twilio.from');

        if ($sid === '' || $token === '' || $from === '') {
            Log::info('Twilio config missing. SMS skipped.', [
                'user_id' => $userId,
                'type' => $type,
                'to' => $to,
                'message' => $message,
            ]);

            $this->recordDelivery(
                userId: $userId,
                type: $type,
                channel: 'sms',
                status: 'failed',
                response: array_merge($meta, ['reason' => 'twilio_not_configured']),
                durationMs: 0
            );

            $metrics->recordNotificationResult('sms', false);

            return false;
        }

        $success = false;

        for ($attempt = 1; $attempt <= $retryAttempts; $attempt++) {
            $start = microtime(true);

            try {
                $response = Http::asForm()
                    ->withBasicAuth($sid, $token)
                    ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                        'To' => $to,
                        'From' => $from,
                        'Body' => $message,
                    ]);

                $durationMs = (microtime(true) - $start) * 1000;
                $attemptSuccess = $response->successful();

                $this->recordDelivery(
                    userId: $userId,
                    type: $type,
                    channel: 'sms',
                    status: $attemptSuccess ? 'sent' : 'failed',
                    response: array_merge($meta, [
                        'attempt' => $attempt,
                        'http_status' => $response->status(),
                        'response' => $response->json(),
                    ]),
                    durationMs: $durationMs
                );

                $metrics->recordNotificationResult('sms', $attemptSuccess);

                if ($attemptSuccess) {
                    $success = true;
                    break;
                }
            } catch (Throwable $exception) {
                $this->recordDelivery(
                    userId: $userId,
                    type: $type,
                    channel: 'sms',
                    status: 'failed',
                    response: array_merge($meta, [
                        'attempt' => $attempt,
                        'exception' => $exception->getMessage(),
                    ]),
                    durationMs: (microtime(true) - $start) * 1000
                );

                $metrics->recordNotificationResult('sms', false);

                Log::error('notification.sms.exception', [
                    'user_id' => $userId,
                    'type' => $type,
                    'attempt' => $attempt,
                    'error' => $exception->getMessage(),
                ]);
            }

            if ($attempt < $retryAttempts) {
                usleep($retryDelayMs * 1000);
            }
        }

        return $success;
    }

    private function sendWithFallback(
        ?User $user,
        ?string $smsTarget,
        string $type,
        string $title,
        string $message,
        array $data
    ): void {
        $userId = $user?->id;
        $pushSucceeded = $user ? $this->sendPushNotification($user, $type, $title, $message, $data) : false;

        if ($pushSucceeded) {
            return;
        }

        $smsSucceeded = $this->sendSms(
            userId: $userId,
            type: $type,
            to: $smsTarget,
            message: trim($title.' - '.str_replace("\n", ' | ', $message)),
            meta: [
                'title' => $title,
                'message' => $message,
                'payload' => $data,
            ]
        );

        if ($smsSucceeded) {
            return;
        }

        ActivityLog::record(null, 'notification.delivery.escalated', [
            'user_id' => $userId,
            'type' => $type,
            'reason' => 'push_and_sms_failed',
        ]);

        Log::critical('notification.delivery.escalated', [
            'user_id' => $userId,
            'type' => $type,
            'reason' => 'push_and_sms_failed',
        ]);
    }

    private function recordDelivery(
        ?int $userId,
        string $type,
        string $channel,
        string $status,
        array $response,
        float $durationMs
    ): void {
        NotificationDelivery::query()->create([
            'user_id' => $userId,
            'type' => Str::limit($type, 100, ''),
            'channel' => $channel,
            'status' => $status,
            'response' => array_merge($response, [
                'duration_ms' => round($durationMs, 2),
            ]),
            'sent_at' => now(),
        ]);

        app(MonitoringMetricsService::class)->recordNotificationDelivery($status === 'sent', $durationMs);
    }

    /**
     * @param array<int, string> $chunkTokens
     * @param array<int, array<string, mixed>> $results
     */
    private function cleanupInvalidTokens(array $chunkTokens, array $results): void
    {
        if (empty($results) || empty($chunkTokens)) {
            return;
        }

        $invalidTokens = [];

        foreach ($results as $index => $result) {
            $error = (string) data_get($result, 'error', '');

            if (! in_array($error, [
                self::TOKEN_ERROR_INVALID,
                self::TOKEN_ERROR_NOT_REGISTERED,
                self::TOKEN_ERROR_MISMATCH,
            ], true)) {
                continue;
            }

            if (array_key_exists($index, $chunkTokens)) {
                $invalidTokens[] = $chunkTokens[$index];
            }
        }

        if (empty($invalidTokens)) {
            return;
        }

        DeviceToken::query()->whereIn('token', $invalidTokens)->delete();

        Log::info('notification.push.invalid_tokens_pruned', [
            'count' => count($invalidTokens),
        ]);
    }
}
