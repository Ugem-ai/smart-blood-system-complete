<?php
namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;

class UniSmsProvider
{
    /**
     * Send a message via UniSMSapi
     * Returns array with success(bool) and provider_id/response or status/body on failure
     */
    public function send(string $to, string $message, ?string $reference = null, ?string $sender = null): array
    {
        $base = config('notifications.unisms.base_url') ?? env('UNISMS_BASE_URL');
        $apiKey = config('notifications.unisms.api_key') ?? env('UNISMS_API_KEY');
        $senderId = $sender ?? config('notifications.unisms.sender_id') ?? env('UNISMS_SENDER_ID');

        $url = rtrim($base ?? 'https://unismsapi.com', '/') . '/messages';

        $payload = [
            'to' => $to,
            'message' => $message,
        ];

        if ($senderId) {
            $payload['from'] = $senderId;
        }

        if ($reference) {
            $payload['reference'] = $reference;
        }

        $response = Http::withToken($apiKey)->post($url, $payload);

        if ($response->successful()) {
            $data = $response->json();
            $id = $data['message_id'] ?? $data['id'] ?? data_get($data, 'data.id');
            return ['success' => true, 'provider_id' => $id, 'response' => $data];
        }

        return ['success' => false, 'status' => $response->status(), 'body' => $response->body()];
    }
}
