<?php

namespace App\Services;

use App\Models\BloodRequest;
use App\Models\Donor;
use App\Models\DonorAlertLog;
use Carbon\Carbon;

class DonorCooldownService
{
    public function maxAlertsPerDay(): int
    {
        return max(1, (int) config('services.notifications.max_alerts_per_day', 3));
    }

    public function cooldownHours(): int
    {
        return max(1, (int) config('services.notifications.cooldown_hours', 12));
    }

    public function canNotifyDonor(Donor $donor, ?Carbon $at = null): bool
    {
        $at ??= now();

        $alertsToday = DonorAlertLog::query()
            ->where('donor_id', $donor->id)
            ->where('sent_at', '>=', $at->copy()->startOfDay())
            ->count();

        if ($alertsToday >= $this->maxAlertsPerDay()) {
            return false;
        }

        $lastAlertAt = DonorAlertLog::query()
            ->where('donor_id', $donor->id)
            ->latest('sent_at')
            ->value('sent_at');

        if (! $lastAlertAt) {
            return true;
        }

        $hoursSinceLastAlert = Carbon::parse($lastAlertAt)->diffInHours($at);

        return $hoursSinceLastAlert >= $this->cooldownHours();
    }

    public function recordAlert(BloodRequest $bloodRequest, Donor $donor, int $escalationLevel): void
    {
        DonorAlertLog::query()->updateOrCreate(
            [
                'blood_request_id' => $bloodRequest->id,
                'donor_id' => $donor->id,
                'escalation_level' => $escalationLevel,
            ],
            [
                'channel' => 'multi',
                'sent_at' => now(),
            ]
        );
    }
}
