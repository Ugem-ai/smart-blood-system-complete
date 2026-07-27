<?php

namespace App\Jobs;

use App\Models\BloodRequest;
use App\Models\Donor;
use App\Models\Hospital;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendHospitalResponseUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 90;

    public bool $failOnTimeout = true;

    public function __construct(
        public int $hospitalId,
        public int $bloodRequestId,
        public int $donorId,
        public string $response,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(NotificationService $notificationService): void
    {
        $hospital = Hospital::find($this->hospitalId);
        $bloodRequest = BloodRequest::find($this->bloodRequestId);
        $donor = Donor::find($this->donorId);

        if (! $hospital || ! $bloodRequest || ! $donor) {
            return;
        }

        try {
            $notificationService->sendHospitalResponseUpdate(
                $hospital,
                $bloodRequest,
                $donor,
                $this->response,
            );
        } catch (Throwable $exception) {
            Log::error('queue.job.send_hospital_response_update.failed', [
                'hospital_id' => $this->hospitalId,
                'blood_request_id' => $this->bloodRequestId,
                'donor_id' => $this->donorId,
                'response' => $this->response,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
