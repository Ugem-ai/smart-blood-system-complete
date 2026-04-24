<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\NationalPartnerSyncLog;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

class NationalSystemsIntegrationService
{
    public function __construct(private readonly EmergencyDashboardService $emergencyDashboardService)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function partners(): array
    {
        $partners = config('services.national_integrations.partners', []);

        if (! is_array($partners)) {
            return [];
        }

        $result = [];

        foreach ($partners as $key => $partner) {
            if (! is_array($partner)) {
                continue;
            }

            $result[] = [
                'key' => (string) $key,
                'label' => (string) ($partner['label'] ?? $key),
                'enabled' => (bool) ($partner['enabled'] ?? false),
                'endpoint_configured' => ! empty($partner['endpoint']),
                'scope' => (string) ($partner['scope'] ?? 'regional'),
            ];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function syncEmergencyDashboard(string $partnerKey, ?int $actorUserId = null, int $limit = 20): array
    {
        $partner = $this->partnerConfig($partnerKey);
        $enabled = (bool) ($partner['enabled'] ?? false);
        $endpoint = (string) ($partner['endpoint'] ?? '');
        $token = (string) ($partner['token'] ?? '');

        $payload = [
            'source' => [
                'system' => config('app.name', 'Smart Blood System'),
                'environment' => config('app.env', 'production'),
                'generated_at' => now()->toIso8601String(),
            ],
            'partner' => [
                'key' => $partnerKey,
                'label' => (string) ($partner['label'] ?? $partnerKey),
                'scope' => (string) ($partner['scope'] ?? 'regional'),
            ],
            'emergency_dashboard' => $this->emergencyDashboardService->snapshot($limit),
        ];

        if (! $enabled) {
            $this->logSync($actorUserId, $partnerKey, 'skipped', null, $payload, ['reason' => 'partner_disabled'], 'Partner integration disabled.');

            return [
                'partner_key' => $partnerKey,
                'status' => 'skipped',
                'message' => 'Partner integration is disabled.',
            ];
        }

        if ($endpoint === '') {
            $this->logSync($actorUserId, $partnerKey, 'skipped', null, $payload, ['reason' => 'endpoint_missing'], 'Partner endpoint is missing.');

            return [
                'partner_key' => $partnerKey,
                'status' => 'skipped',
                'message' => 'Partner endpoint is not configured.',
            ];
        }

        $timeout = max(1, (int) config('services.national_integrations.timeout_seconds', 10));

        try {
            $request = Http::timeout($timeout)->acceptJson();
            if ($token !== '') {
                $request = $request->withToken($token);
            }

            $response = $request->post($endpoint, $payload);

            $status = $response->successful() ? 'success' : 'failed';
            $responsePayload = [
                'body' => $response->json(),
            ];

            $this->logSync(
                $actorUserId,
                $partnerKey,
                $status,
                $response->status(),
                $payload,
                $responsePayload,
                $response->successful() ? null : ('Partner returned HTTP '.$response->status())
            );

            ActivityLog::record($actorUserId, 'national-integration.sync-emergency-dashboard', [
                'partner_key' => $partnerKey,
                'status' => $status,
                'http_status' => $response->status(),
            ]);

            return [
                'partner_key' => $partnerKey,
                'status' => $status,
                'http_status' => $response->status(),
                'message' => $response->successful()
                    ? 'Emergency dashboard synced successfully.'
                    : 'Emergency dashboard sync failed.',
            ];
        } catch (Throwable $exception) {
            $this->logSync(
                $actorUserId,
                $partnerKey,
                'failed',
                null,
                $payload,
                null,
                $exception->getMessage()
            );

            ActivityLog::record($actorUserId, 'national-integration.sync-emergency-dashboard', [
                'partner_key' => $partnerKey,
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ]);

            return [
                'partner_key' => $partnerKey,
                'status' => 'failed',
                'message' => 'Emergency dashboard sync failed due to transport error.',
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function partnerConfig(string $partnerKey): array
    {
        $partners = config('services.national_integrations.partners', []);

        if (! is_array($partners) || ! array_key_exists($partnerKey, $partners) || ! is_array($partners[$partnerKey])) {
            throw new InvalidArgumentException('Unknown national integration partner: '.$partnerKey);
        }

        return $partners[$partnerKey];
    }

    /**
     * @param array<string, mixed>|null $responsePayload
     * @param array<string, mixed> $requestPayload
     */
    private function logSync(
        ?int $actorUserId,
        string $partnerKey,
        string $status,
        ?int $httpStatus,
        array $requestPayload,
        ?array $responsePayload,
        ?string $errorMessage
    ): void {
        NationalPartnerSyncLog::query()->create([
            'actor_user_id' => $actorUserId,
            'partner_key' => $partnerKey,
            'status' => $status,
            'http_status' => $httpStatus,
            'request_payload' => $requestPayload,
            'response_payload' => $responsePayload,
            'error_message' => $errorMessage,
            'synced_at' => now(),
        ]);
    }
}
