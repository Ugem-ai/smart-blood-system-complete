<?php
namespace App\Jobs;

use App\Models\OutboundNotification;
use App\Services\Notifications\UniSmsProvider;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOutboundNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $notificationId;

    public $tries = 5;

    public function __construct(int $notificationId)
    {
        $this->notificationId = $notificationId;
    }

    public function handle()
    {
        $record = OutboundNotification::find($this->notificationId);
        if (!$record) return;

        $provider = new UniSmsProvider();

        $payload = $record->payload ?? [];
        $to = $record->to;
        $message = data_get($payload, 'message') ?? json_encode($payload);

        try {
            $result = $provider->send($to, $message, (string)$record->id);

            $record->attempts = $record->attempts + 1;
            $record->provider = 'unisms';

            if ($result['success']) {
                $record->status = 'delivered';
                $record->provider_message_id = $result['provider_id'] ?? null;
                $record->delivered_at = now();
                $record->save();
                return;
            }

            // failed - increment attempts and throw to let queue retry
            $record->status = 'failed';
            $record->save();
            throw new Exception('Provider send failed: ' . ($result['status'] ?? json_encode($result['body'] ?? '')));

        } catch (Exception $e) {
            $record->attempts = $record->attempts + 1;
            $record->status = 'pending';
            $record->save();

            $delay = pow(2, min($this->attempts ?? 1, 6)) * 60; // exponential backoff minutes
            $this->release($delay);
        }
    }
}
