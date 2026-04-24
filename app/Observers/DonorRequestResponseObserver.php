<?php

namespace App\Observers;

use App\Models\DonorRequestResponse;
use App\Services\ReliabilityScoreService;

class DonorRequestResponseObserver
{
    public function __construct(private ReliabilityScoreService $reliabilityService) {}

    public function created(DonorRequestResponse $response): void
    {
        if ($response->donor) {
            $this->reliabilityService->update($response->donor);
        }
    }

    public function updated(DonorRequestResponse $response): void
    {
        if ($response->donor) {
            $this->reliabilityService->update($response->donor);
        }
    }
}
