<?php

namespace App\Observers;

use App\Models\DonationHistory;
use App\Services\ReliabilityScoreService;

class DonationHistoryObserver
{
    public function __construct(private ReliabilityScoreService $reliabilityService) {}

    public function created(DonationHistory $history): void
    {
        if ($history->donor) {
            $this->reliabilityService->update($history->donor);
        }
    }

    public function updated(DonationHistory $history): void
    {
        if ($history->donor) {
            $this->reliabilityService->update($history->donor);
        }
    }
}
