<?php

namespace App\Jobs;

use App\Models\BloodRequest;
use App\Models\Donor;
use App\Services\BloodRequestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessDonorResponseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 90;

    public bool $failOnTimeout = true;

    public function __construct(
        public int $bloodRequestId,
        public int $donorId,
        public string $response,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(BloodRequestService $bloodRequestService): void
    {
        $bloodRequest = BloodRequest::query()->with('hospital')->find($this->bloodRequestId);
        $donor = Donor::find($this->donorId);

        if (! $bloodRequest || ! $donor) {
            return;
        }

        if ($bloodRequest->hospital) {
            SendHospitalResponseUpdateJob::dispatch(
                $bloodRequest->hospital->id,
                $bloodRequest->id,
                $donor->id,
                $this->response,
            )->onQueue('notifications');
        }

        $bloodRequestService->syncTrackingCounts($bloodRequest->fresh());
    }
}
