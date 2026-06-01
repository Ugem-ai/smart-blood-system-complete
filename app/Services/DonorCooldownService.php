<?php

namespace App\Services;

use App\Models\BloodRequest;
use App\Models\Donor;
use App\Models\DonorAlertLog;
use Carbon\Carbon;

class DonorCooldownService
{
    // Minimum days between donations (WHO standard)
    private const DONATION_INTERVAL_DAYS = 56;

    public function maxAlertsPerDay(): int
    {
        return max(1, (int) config('services.notifications.max_alerts_per_day', 3));
    }

    public function cooldownHours(): int
    {
        return max(1, (int) config('services.notifications.cooldown_hours', 12));
    }

    /**
     * Check if donor is physically eligible to donate (56-day recovery).
     */
    public function isDonationEligible(Donor $donor): bool
    {
        return $donor->isEligibleForDonation(self::DONATION_INTERVAL_DAYS);
    }

    /**
     * Get donor fatigue status for dashboard display.
     *
     * @return array{
     *   eligible: bool,
     *   days_since_last_donation: int|null,
     *   days_until_eligible: int|null,
     *   next_eligible_date: string|null,
     *   fatigue_level: string,
     *   message: string
     * }
     */
    public function getFatigueStatus(Donor $donor): array
    {
        $daysSince = $donor->daysSinceLastDonation();
        $eligible = $donor->isEligibleForDonation(self::DONATION_INTERVAL_DAYS);
        $nextEligibleDate = $donor->nextEligibleDonationDate(self::DONATION_INTERVAL_DAYS);

        if ($daysSince === null) {
            return [
                'eligible' => true,
                'days_since_last_donation' => null,
                'days_until_eligible' => null,
                'next_eligible_date' => null,
                'fatigue_level' => 'none',
                'message' => 'No donation history. You are eligible to donate.',
            ];
        }

        $daysUntilEligible = $eligible ? 0 : (self::DONATION_INTERVAL_DAYS - $daysSince);

        // Determine fatigue level
        $fatigueLevel = match (true) {
            ! $eligible && $daysSince < 14  => 'critical',   // Less than 2 weeks
            ! $eligible && $daysSince < 28  => 'high',       // Less than 4 weeks
            ! $eligible && $daysSince < 42  => 'moderate',   // Less than 6 weeks
            ! $eligible                     => 'low',        // Almost eligible
            default                         => 'none',       // Fully eligible
        };

        $message = match ($fatigueLevel) {
            'critical'  => "You donated {$daysSince} days ago. You need at least ".self::DONATION_INTERVAL_DAYS.' days to recover before donating again.',
            'high'      => "You donated {$daysSince} days ago. Please wait {$daysUntilEligible} more days before donating.",
            'moderate'  => "Almost there! You need {$daysUntilEligible} more days before you can donate again.",
            'low'       => "You are almost eligible! Only {$daysUntilEligible} more days remaining.",
            default     => "You last donated {$daysSince} days ago. You are eligible to donate.",
        };

        return [
            'eligible' => $eligible,
            'days_since_last_donation' => $daysSince,
            'days_until_eligible' => $daysUntilEligible,
            'next_eligible_date' => $nextEligibleDate?->toDateString(),
            'fatigue_level' => $fatigueLevel,
            'message' => $message,
        ];
    }

    /**
     * Main gate: can we send a notification to this donor?
     * Checks BOTH donation eligibility AND alert cooldown.
     */
    public function canNotifyDonor(Donor $donor, ?Carbon $at = null): bool
    {
        // Block if donor is not physically eligible (56-day recovery)
        if (! $this->isDonationEligible($donor)) {
            return false;
        }

        $at ??= now();

        // Block if donor has received too many alerts today
        $alertsToday = DonorAlertLog::query()
            ->where('donor_id', $donor->id)
            ->where('sent_at', '>=', $at->copy()->startOfDay())
            ->count();

        if ($alertsToday >= $this->maxAlertsPerDay()) {
            return false;
        }

        // Block if donor was alerted too recently
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

    /**
     * Get a detailed reason why a donor cannot be notified (for logging/debugging).
     */
    public function getBlockReason(Donor $donor, ?Carbon $at = null): ?string
    {
        if (! $this->isDonationEligible($donor)) {
            $days = $donor->daysSinceLastDonation();
            $remaining = self::DONATION_INTERVAL_DAYS - $days;
            return "Donor not eligible: donated {$days} days ago, needs {$remaining} more days recovery.";
        }

        $at ??= now();

        $alertsToday = DonorAlertLog::query()
            ->where('donor_id', $donor->id)
            ->where('sent_at', '>=', $at->copy()->startOfDay())
            ->count();

        if ($alertsToday >= $this->maxAlertsPerDay()) {
            return "Donor reached max alerts per day ({$alertsToday}/{$this->maxAlertsPerDay()}).";
        }

        $lastAlertAt = DonorAlertLog::query()
            ->where('donor_id', $donor->id)
            ->latest('sent_at')
            ->value('sent_at');

        if ($lastAlertAt) {
            $hoursSince = Carbon::parse($lastAlertAt)->diffInHours($at);
            if ($hoursSince < $this->cooldownHours()) {
                $remaining = $this->cooldownHours() - $hoursSince;
                return "Donor in cooldown: {$hoursSince}h since last alert, needs {$remaining}h more.";
            }
        }

        return null; // No block reason — donor can be notified
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