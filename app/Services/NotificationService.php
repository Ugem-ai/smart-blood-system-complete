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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class NotificationService
{
    private const TOKEN_ERROR_INVALID = 'InvalidRegistration';

    private const TOKEN_ERROR_NOT_REGISTERED = 'NotRegistered';

    private const TOKEN_ERROR_MISMATCH = 'MismatchSenderId';

    public function sendDonorAlert(
        Donor $donor,
        BloodRequest $bloodRequest,
        ?float $distanceKm = null,
        bool $forceSend = false,
        ?string $preferredChannel = null
    ): void
    {
        $cooldownService = app(DonorCooldownService::class);

        if (! $forceSend && ! $cooldownService->canNotifyDonor($donor)) {
            ActivityLog::record(null, 'notification.throttled.cooldown', [
                'donor_id' => $donor->id,
                'blood_request_id' => $bloodRequest->id,
            ]);

            return;
        }

        if ($forceSend) {
            ActivityLog::record(null, 'notification.force-resend', [
                'donor_id' => $donor->id,
                'blood_request_id' => $bloodRequest->id,
                'reason' => 'admin_resend_action',
            ]);
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
            data: $payload,
            preferredChannel: $preferredChannel
        );
    }

    public function sendTravelAcceptanceRequest(
        Donor $donor,
        BloodRequest $bloodRequest,
        float $distanceKm
    ): void {
        $title = 'Emergency Travel Confirmation Needed';
        $message = sprintf(
            "Emergency request for %s at %s is %.2fkm away. Can you travel farther than your normal radius for this request?",
            $bloodRequest->blood_type,
            $bloodRequest->hospital_name,
            $distanceKm
        );

        $payload = [
            'type' => 'emergency_travel_acceptance_request',
            'blood_request_id' => $bloodRequest->id,
            'donor_id' => $donor->id,
            'distance_km' => round($distanceKm, 2),
            'action' => '/api/blood-requests/'.$bloodRequest->id.'/acceptances',
        ];

        $this->sendWithFallback(
            user: $donor->user,
            smsTarget: $donor->phone ?? $donor->contact_number,
            type: 'emergency_travel_acceptance_request',
            title: $title,
            message: $message,
            data: $payload,
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

    public function sendCustomDonorMessage(
        Donor $donor,
        BloodRequest $bloodRequest,
        string $message,
        string $title = 'Manual Admin Message',
        ?string $preferredChannel = null
    ): void
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
            ],
            preferredChannel: $preferredChannel
        );
    }

    public function notificationHealth(): array
    {
        $oneSignalConfigured = $this->isOneSignalConfigured();
        $fcmConfigured = $this->isFcmConfigured();
        $pushConfigured = $oneSignalConfigured || $fcmConfigured;
        $smsConfigured = $this->isTwilioConfigured() || $this->isUnismsConfigured();
        $smsProvider = $this->smsProvider();
        $emailConfigured = $this->isEmailConfigured();

        $warnings = [];

        if (! $pushConfigured) {
            $warnings[] = 'Push notifications are not configured.';
        }

        if (! $smsConfigured) {
            $warnings[] = 'SMS notifications are not configured.';
        }

        if (! $emailConfigured) {
            $warnings[] = 'Email notifications are not configured.';
        }

        if (! $pushConfigured && ! $smsConfigured && ! $emailConfigured) {
            $warnings[] = 'No notification transport is configured; donor alerts cannot be delivered.';
        }

        return [
            'ready' => $pushConfigured || $smsConfigured || $emailConfigured,
            'push_configured' => $pushConfigured,
            'push_onesignal_configured' => $oneSignalConfigured,
            'push_fcm_configured' => $fcmConfigured,
            'sms_configured' => $smsConfigured,
            'email_configured' => $emailConfigured,
            'sms_provider' => $smsProvider,
            'warnings' => $warnings,
            'summary' => $pushConfigured || $smsConfigured || $emailConfigured
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

    public function sendDonorReadinessCheck(Donor $donor): bool
    {
        $smsTarget = $donor->phone ?? $donor->contact_number;
        $email = $donor->email ?? $donor->user?->email;
        $hasDeviceToken = DeviceToken::query()->where('user_id', $donor->user_id)->exists();
        
        if (!$hasDeviceToken && !$smsTarget && !$email) {
            Log::warning('notification.readiness_check.skipped_no_contact', [
                'donor_id' => $donor->id,
                'user_id' => $donor->user_id,
                'reason' => 'no_contact_methods_available',
            ]);
            return false;
        }
        
        $this->sendWithFallback(
            user: $donor->user,
            smsTarget: $smsTarget,
            type: 'admin_donor_readiness_ping',
            title: 'Donor Readiness Check',
            message: 'A blood operations coordinator requested a readiness update. Please confirm your availability.',
            data: [
                'type' => 'admin_donor_readiness_ping',
                'donor_id' => $donor->id,
            ]
        );
        return true;
    }

    public function sendPushNotification(User $user, string $type, string $title, string $message, array $data = []): bool
    {
        $metrics = app(MonitoringMetricsService::class);
        $appId = trim((string) config('services.onesignal.app_id', ''));
        $restApiKey = trim((string) config('services.onesignal.rest_api_key', ''));
        $oneSignalEndpoint = (string) config('services.onesignal.endpoint', 'https://api.onesignal.com/notifications?c=push');
        $fcmServerKey = trim((string) config('services.fcm.server_key', ''));
        $fcmEndpoint = (string) config('services.fcm.endpoint', 'https://fcm.googleapis.com/fcm/send');
        $batchSize = max(1, (int) config('services.notifications.push_batch_size', 100));
        $batchPacingUs = max(0, (int) config('services.notifications.push_batch_pacing_us', 100000));
        $tokens = DeviceToken::query()
            ->where('user_id', $user->id)
            ->pluck('token')
            ->filter()
            ->values();
        $isOneSignalConfigured = $appId !== '' && $restApiKey !== '';
        $isFcmConfigured = $fcmServerKey !== '';

        if (! $isOneSignalConfigured && ! $isFcmConfigured) {
            $this->recordDelivery(
                userId: $user->id,
                type: $type,
                channel: 'push',
                status: 'skipped',
                response: ['reason' => 'push_provider_not_configured'],
                durationMs: 0
            );

            Log::info('notification.push.skipped_unconfigured', [
                'user_id' => $user->id,
                'type' => $type,
            ]);

            $metrics->recordNotificationResult('push', false);

            return false;
        }

        try {
            if ($isFcmConfigured) {
                $start = microtime(true);

                $response = Http::withHeaders([
                    'Authorization' => 'key='.$fcmServerKey,
                ])
                    ->acceptJson()
                    ->post($fcmEndpoint, [
                        'registration_ids' => $tokens->all(),
                        'notification' => [
                            'title' => $title,
                            'body' => $message,
                        ],
                        'data' => $data,
                    ]);

                $durationMs = (microtime(true) - $start) * 1000;
                $responseBody = $response->json();
                $pushSuccessful = $response->successful()
                    && (int) data_get($responseBody, 'failure', 0) === 0
                    && $tokens->isNotEmpty();

                $payload = [
                    'title' => $title,
                    'message' => $message,
                    'payload' => $data,
                    'provider' => 'fcm',
                    'http_status' => $response->status(),
                    'response' => $responseBody,
                    'token_batch_size' => $tokens->count(),
                ];

                if ($tokens->isEmpty()) {
                    $payload['reason'] = 'no_device_tokens';
                }

                $this->recordDelivery(
                    userId: $user->id,
                    type: $type,
                    channel: 'push',
                    status: $pushSuccessful ? 'sent' : 'failed',
                    response: $payload,
                    durationMs: $durationMs
                );

                $metrics->recordNotificationResult('push', $pushSuccessful);

                if ($pushSuccessful) {
                    DeviceToken::query()
                        ->where('user_id', $user->id)
                        ->whereIn('token', $tokens->all())
                        ->update(['last_used_at' => now()]);
                }

                return $pushSuccessful;
            }

            if ($tokens->isEmpty()) {
                $this->recordDelivery(
                    userId: $user->id,
                    type: $type,
                    channel: 'push',
                    status: 'skipped',
                    response: ['reason' => 'no_device_tokens'],
                    durationMs: 0
                );

                Log::info('notification.push.skipped', [
                    'user_id' => $user->id,
                    'type' => $type,
                    'reason' => 'no_device_tokens',
                ]);

                $metrics->recordNotificationResult('push', false);

                return false;
            }

            $allSuccessful = true;

            foreach ($tokens->chunk($batchSize) as $chunk) {
                $start = microtime(true);
                $chunkTokens = $chunk->values()->all();

                $response = Http::withHeaders([
                    'Authorization' => 'Key '.$restApiKey,
                ])
                    ->acceptJson()
                    ->post($oneSignalEndpoint, [
                        'app_id' => $appId,
                        'include_subscription_ids' => $chunkTokens,
                        'target_channel' => 'push',
                        'headings' => [
                            'en' => $title,
                        ],
                        'contents' => [
                            'en' => $message,
                        ],
                        'data' => $data,
                    ]);

                $durationMs = (microtime(true) - $start) * 1000;
                $responseBody = $response->json();

                $payload = [
                    'title' => $title,
                    'message' => $message,
                    'payload' => $data,
                    'http_status' => $response->status(),
                    'response' => $responseBody,
                    'token_batch_size' => count($chunkTokens),
                ];

                $batchSuccessful = $response->successful() && blank(data_get($responseBody, 'errors'));

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

            app(MonitoringMetricsService::class)->recordNotificationResult('sms', false);

            return false;
        }

        $provider = $this->smsProvider();

        if ($provider === 'twilio') {
            return $this->sendSmsViaTwilio($userId, $type, $to, $message, $meta, $retryAttempts, $retryDelayMs);
        }

        if ($provider === 'unisms') {
            return $this->sendSmsViaUnisms($userId, $type, $to, $message, $meta, $retryAttempts, $retryDelayMs);
        }

        Log::info('SMS provider is not configured. SMS skipped.', [
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
            response: array_merge($meta, ['reason' => 'sms_provider_not_configured']),
            durationMs: 0
        );

        app(MonitoringMetricsService::class)->recordNotificationResult('sms', false);

        return false;
    }

    public function sendEmail(?int $userId, string $type, ?string $to, string $subject, string $body, array $meta = []): bool
    {
        if (! $to) {
            $this->recordDelivery(
                userId: $userId,
                type: $type,
                channel: 'email',
                status: 'failed',
                response: array_merge($meta, ['reason' => 'missing_recipient']),
                durationMs: 0
            );

            app(MonitoringMetricsService::class)->recordNotificationResult('email', false);

            return false;
        }

        if (! $this->isEmailConfigured()) {
            Log::info('Email transport is not configured. Email skipped.', [
                'user_id' => $userId,
                'type' => $type,
                'to' => $to,
                'subject' => $subject,
            ]);

            $this->recordDelivery(
                userId: $userId,
                type: $type,
                channel: 'email',
                status: 'failed',
                response: array_merge($meta, ['reason' => 'email_not_configured']),
                durationMs: 0
            );

            app(MonitoringMetricsService::class)->recordNotificationResult('email', false);

            return false;
        }

        $start = microtime(true);

        try {
            Mail::raw($body, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);

                $fromAddress = trim((string) config('mail.from.address', ''));
                $fromName = trim((string) config('mail.from.name', ''));

                if ($fromAddress !== '') {
                    $message->from($fromAddress, $fromName !== '' ? $fromName : null);
                }
            });

            $durationMs = (microtime(true) - $start) * 1000;

            $this->recordDelivery(
                userId: $userId,
                type: $type,
                channel: 'email',
                status: 'sent',
                response: array_merge($meta, [
                    'subject' => $subject,
                    'body' => $body,
                    'recipient' => $to,
                ]),
                durationMs: $durationMs
            );

            app(MonitoringMetricsService::class)->recordNotificationResult('email', true);

            return true;
        } catch (Throwable $exception) {
            $durationMs = (microtime(true) - $start) * 1000;

            $this->recordDelivery(
                userId: $userId,
                type: $type,
                channel: 'email',
                status: 'failed',
                response: array_merge($meta, [
                    'subject' => $subject,
                    'body' => $body,
                    'recipient' => $to,
                    'exception' => $exception->getMessage(),
                ]),
                durationMs: $durationMs
            );

            app(MonitoringMetricsService::class)->recordNotificationResult('email', false);

            Log::error('notification.email.exception', [
                'user_id' => $userId,
                'type' => $type,
                'recipient' => $to,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function shouldSkipPushDueToStaleDeviceTokens(User $user): bool
    {
        $thresholdMinutes = max(0, (int) config('services.notifications.push_stale_threshold_minutes', 30));

        if ($thresholdMinutes === 0) {
            return false;
        }

        $freshThreshold = now()->subMinutes($thresholdMinutes);

        $hasFreshDeviceToken = DeviceToken::query()
            ->where('user_id', $user->id)
            ->whereNotNull('last_used_at')
            ->where('last_used_at', '>=', $freshThreshold)
            ->exists();

        return ! $hasFreshDeviceToken && DeviceToken::query()->where('user_id', $user->id)->exists();
    }

    private function smsProvider(): string
    {
        $requested = strtolower(trim((string) config('services.notifications.sms_provider', 'auto')));

        if ($requested === 'twilio' && $this->isTwilioConfigured()) {
            return 'twilio';
        }

        if ($requested === 'unisms' && $this->isUnismsConfigured()) {
            return 'unisms';
        }

        if ($requested === 'auto') {
            if ($this->isUnismsConfigured()) {
                return 'unisms';
            }

            if ($this->isTwilioConfigured()) {
                return 'twilio';
            }
        }

        if ($this->isUnismsConfigured()) {
            return 'unisms';
        }

        if ($this->isTwilioConfigured()) {
            return 'twilio';
        }

        return 'none';
    }

    private function isTwilioConfigured(): bool
    {
        return trim((string) config('services.twilio.sid', '')) !== ''
            && trim((string) config('services.twilio.token', '')) !== ''
            && trim((string) config('services.twilio.from', '')) !== '';
    }

    private function isUnismsConfigured(): bool
    {
        return trim((string) config('services.unisms.api_key', '')) !== ''
            && trim((string) config('services.unisms.endpoint', '')) !== '';
    }

    private function isOneSignalConfigured(): bool
    {
        return trim((string) config('services.onesignal.app_id', '')) !== ''
            && trim((string) config('services.onesignal.rest_api_key', '')) !== '';
    }

    private function isFcmConfigured(): bool
    {
        return trim((string) config('services.fcm.server_key', '')) !== '';
    }

    private function isEmailConfigured(): bool
    {
        return trim((string) config('mail.default', '')) !== ''
            && trim((string) config('mail.from.address', '')) !== '';
    }

    private function sendSmsViaTwilio(?int $userId, string $type, string $to, string $message, array $meta, int $retryAttempts, int $retryDelayMs): bool
    {
        $metrics = app(MonitoringMetricsService::class);
        $sid = (string) config('services.twilio.sid');
        $token = (string) config('services.twilio.token');
        $from = (string) config('services.twilio.from');
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
                        'provider' => 'twilio',
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
                        'provider' => 'twilio',
                        'exception' => $exception->getMessage(),
                    ]),
                    durationMs: (microtime(true) - $start) * 1000
                );

                $metrics->recordNotificationResult('sms', false);

                Log::error('notification.sms.exception', [
                    'user_id' => $userId,
                    'type' => $type,
                    'provider' => 'twilio',
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

    private function sendSmsViaUnisms(?int $userId, string $type, string $to, string $message, array $meta, int $retryAttempts, int $retryDelayMs): bool
    {
        $metrics = app(MonitoringMetricsService::class);
        $apiKey = (string) config('services.unisms.api_key');
        $senderId = (string) config('services.unisms.sender_id');
        $endpoint = (string) config('services.unisms.endpoint');
        $success = false;

        for ($attempt = 1; $attempt <= $retryAttempts; $attempt++) {
            $start = microtime(true);

            try {
                $body = [
                    'recipient' => $to,
                    'content' => $message,
                ];

                if ($senderId !== '') {
                    $body['sender_id'] = $senderId;
                }

                $response = Http::acceptJson()
                    ->withBasicAuth($apiKey, '')
                    ->post($endpoint, $body);

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
                        'provider' => 'unisms',
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
                        'provider' => 'unisms',
                        'exception' => $exception->getMessage(),
                    ]),
                    durationMs: (microtime(true) - $start) * 1000
                );

                $metrics->recordNotificationResult('sms', false);

                Log::error('notification.sms.exception', [
                    'user_id' => $userId,
                    'type' => $type,
                    'provider' => 'unisms',
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
        array $data,
        ?string $preferredChannel = null
    ): void {
        $userId = $user?->id;
        $normalizedChannel = $preferredChannel ? strtolower(trim($preferredChannel)) : null;

        if (in_array($normalizedChannel, ['sms', 'email'], true)) {
            if ($normalizedChannel === 'sms') {
                $smsSucceeded = $this->sendSms(
                    userId: $userId,
                    type: $type,
                    to: $smsTarget,
                    message: trim($title.' - '.str_replace("\n", ' | ', $message)),
                    meta: [
                        'title' => $title,
                        'message' => $message,
                        'payload' => $data,
                        'preferred_channel' => 'sms',
                    ]
                );

                if ($smsSucceeded) {
                    return;
                }
            }

            if ($normalizedChannel === 'email') {
                $emailSucceeded = $this->sendEmail(
                    userId: $userId,
                    type: $type,
                    to: $user?->email,
                    subject: $title,
                    body: trim($title.' - '.str_replace("\n", ' | ', $message)),
                    meta: [
                        'title' => $title,
                        'message' => $message,
                        'payload' => $data,
                        'preferred_channel' => 'email',
                    ]
                );

                if ($emailSucceeded) {
                    return;
                }
            }

            ActivityLog::record(null, 'notification.delivery.channel_forced_failed', [
                'user_id' => $userId,
                'type' => $type,
                'forced_channel' => $normalizedChannel,
                'reason' => 'channel_delivery_failed',
            ]);

            Log::warning('notification.delivery.channel_forced_failed', [
                'user_id' => $userId,
                'type' => $type,
                'forced_channel' => $normalizedChannel,
            ]);

            return;
        }

        $pushSucceeded = false;

        if ($user && ! $this->shouldSkipPushDueToStaleDeviceTokens($user)) {
            $pushSucceeded = $this->sendPushNotification($user, $type, $title, $message, $data);
        } elseif ($user) {
            Log::info('notification.push.bypassed_stale_tokens', [
                'user_id' => $user->id,
                'threshold_minutes' => (int) config('services.notifications.push_stale_threshold_minutes', 30),
            ]);
        }

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

        $emailSucceeded = $this->sendEmail(
            userId: $userId,
            type: $type,
            to: $user?->email,
            subject: $title,
            body: trim($title.' - '.str_replace("\n", ' | ', $message)),
            meta: [
                'title' => $title,
                'message' => $message,
                'payload' => $data,
            ]
        );

        if ($emailSucceeded) {
            return;
        }

        ActivityLog::record(null, 'notification.delivery.escalated', [
            'user_id' => $userId,
            'type' => $type,
            'reason' => 'push_and_sms_and_email_failed',
        ]);

        Log::critical('notification.delivery.escalated', [
            'user_id' => $userId,
            'type' => $type,
            'reason' => 'push_and_sms_and_email_failed',
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
