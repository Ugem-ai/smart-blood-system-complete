<?php

namespace App\Services;

use App\Models\Donor;
use App\Models\DonorRequestResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DonorNotificationTimingService
{
    /**
     * @var array<int, array{total: int, accepted: int, by_hour: array<int, array{total: int, accepted: int}>}>
     */
    private array $profiles = [];

    /**
     * @return array{send_at: Carbon, best_hour: int|null, current_hour: int, acceptance_probability_current: float, acceptance_probability_best: float, response_samples: int}
     */
    public function planForDonor(Donor $donor, Carbon $referenceAt, int $maxDelayMinutes = 90): array
    {
        $profile = $this->profile($donor);
        $currentHour = (int) $referenceAt->copy()->format('G');
        $predictedUnavailableAtWorkHours = $this->isWorkHoursUnavailableFromProfile($profile);
        $bestHour = $this->bestHourFromProfile($profile, $predictedUnavailableAtWorkHours);

        $currentProbability = $this->acceptanceProbabilityForHour($profile, $currentHour);
        $bestProbability = $bestHour !== null
            ? $this->acceptanceProbabilityForHour($profile, $bestHour)
            : $currentProbability;

        $sendAt = $referenceAt->copy();

        if ($bestHour !== null && $this->shouldDelay($currentProbability, $bestProbability)) {
            $candidate = $referenceAt->copy()->setMinute(0)->setSecond(0)->setHour($bestHour);
            if ($candidate->lte($referenceAt)) {
                $candidate->addDay();
            }

            $delayMinutes = $referenceAt->diffInMinutes($candidate);

            if ($delayMinutes > 0 && $delayMinutes <= max(0, $maxDelayMinutes)) {
                $sendAt = $candidate;
            }
        }

        return [
            'send_at' => $sendAt,
            'best_hour' => $bestHour,
            'current_hour' => $currentHour,
            'acceptance_probability_current' => round($currentProbability, 4),
            'acceptance_probability_best' => round($bestProbability, 4),
            'response_samples' => $profile['total'],
        ];
    }

    public function isPredictedUnavailableNow(Donor $donor, ?Carbon $referenceAt = null): bool
    {
        $referenceAt ??= now();

        if (! $this->isWithinConfiguredWorkHours((int) $referenceAt->format('G'))) {
            return false;
        }

        return $this->isWorkHoursUnavailableFromProfile($this->profile($donor));
    }

    /**
     * @return array{top_response_hours: array<int, array{hour: int, total_responses: int, accepted_responses: int, acceptance_probability: float}>, donor_profiles: array<int, array{donor_id: int, donor_name: string, best_hour: int|null, acceptance_probability_best: float, acceptance_probability_overall: float, response_samples: int}>}
     */
    public function dashboardInsights(int $topHours = 5, int $topDonors = 10): array
    {
        $hourlyRows = DonorRequestResponse::query()
            ->whereNotNull('responded_at')
            ->selectRaw($this->hourSelectExpression().' as hour')
            ->selectRaw('COUNT(*) as total_responses')
            ->selectRaw("SUM(CASE WHEN response = 'accepted' THEN 1 ELSE 0 END) as accepted_responses")
            ->groupBy('hour')
            ->orderByDesc(DB::raw("SUM(CASE WHEN response = 'accepted' THEN 1 ELSE 0 END) * 1.0 / COUNT(*)"))
            ->orderByDesc('total_responses')
            ->limit(max(1, $topHours))
            ->get();

        $topResponseHours = $hourlyRows
            ->map(fn ($row) => [
                'hour' => (int) $row->hour,
                'total_responses' => (int) $row->total_responses,
                'accepted_responses' => (int) $row->accepted_responses,
                'acceptance_probability' => (float) round(
                    ((int) $row->accepted_responses) / max(1, (int) $row->total_responses),
                    4
                ),
            ])
            ->values()
            ->all();

        $candidateDonorIds = DonorRequestResponse::query()
            ->whereNotNull('responded_at')
            ->selectRaw('donor_id, COUNT(*) as total_responses')
            ->groupBy('donor_id')
            ->orderByDesc('total_responses')
            ->limit(max(1, $topDonors))
            ->pluck('donor_id')
            ->all();

        $donorProfiles = Donor::query()
            ->whereIn('id', $candidateDonorIds)
            ->get(['id', 'name'])
            ->map(function (Donor $donor) {
                $profile = $this->profile($donor);
                $bestHour = $this->bestHourFromProfile($profile);
                $overallProbability = $profile['total'] > 0
                    ? $profile['accepted'] / $profile['total']
                    : 0.0;

                return [
                    'donor_id' => $donor->id,
                    'donor_name' => $donor->name,
                    'best_hour' => $bestHour,
                    'acceptance_probability_best' => $bestHour !== null
                        ? round($this->acceptanceProbabilityForHour($profile, $bestHour), 4)
                        : round($overallProbability, 4),
                    'acceptance_probability_overall' => round($overallProbability, 4),
                    'response_samples' => $profile['total'],
                ];
            })
            ->sortByDesc('acceptance_probability_best')
            ->values()
            ->all();

        return [
            'top_response_hours' => $topResponseHours,
            'donor_profiles' => $donorProfiles,
        ];
    }

    private function shouldDelay(float $currentProbability, float $bestProbability): bool
    {
        return ($bestProbability - $currentProbability) >= 0.20;
    }

    /**
     * @return array{total: int, accepted: int, by_hour: array<int, array{total: int, accepted: int}>}
     */
    private function profile(Donor $donor): array
    {
        if (isset($this->profiles[$donor->id])) {
            return $this->profiles[$donor->id];
        }

        $responses = DonorRequestResponse::query()
            ->where('donor_id', $donor->id)
            ->whereNotNull('responded_at')
            ->get(['response', 'responded_at']);

        $total = 0;
        $accepted = 0;
        $byHour = [];

        foreach ($responses as $response) {
            $hour = (int) Carbon::parse($response->responded_at)->format('G');
            $isAccepted = $response->response === 'accepted';

            $total++;
            if ($isAccepted) {
                $accepted++;
            }

            if (! isset($byHour[$hour])) {
                $byHour[$hour] = ['total' => 0, 'accepted' => 0];
            }

            $byHour[$hour]['total']++;
            if ($isAccepted) {
                $byHour[$hour]['accepted']++;
            }
        }

        return $this->profiles[$donor->id] = [
            'total' => $total,
            'accepted' => $accepted,
            'by_hour' => $byHour,
        ];
    }

    private function bestHourFromProfile(array $profile, bool $excludeWorkHours = false): ?int
    {
        if (empty($profile['by_hour'])) {
            return null;
        }

        $bestHour = null;
        $bestScore = -1;

        foreach ($profile['by_hour'] as $hour => $stats) {
            $hourInt = (int) $hour;

            if ($excludeWorkHours && $this->isWithinConfiguredWorkHours($hourInt)) {
                continue;
            }

            $score = $this->acceptanceProbabilityForHour($profile, $hourInt);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestHour = $hourInt;
            }
        }

        return $bestHour;
    }

    private function isWorkHoursUnavailableFromProfile(array $profile): bool
    {
        $minSamples = max(1, (int) config('services.notifications.prediction_min_samples', 3));
        $declineRateThreshold = (float) config('services.notifications.prediction_decline_rate', 0.7);

        $workHourTotal = 0;
        $workHourAccepted = 0;

        foreach ($profile['by_hour'] as $hour => $stats) {
            $hourInt = (int) $hour;

            if (! $this->isWithinConfiguredWorkHours($hourInt)) {
                continue;
            }

            $workHourTotal += max(0, (int) ($stats['total'] ?? 0));
            $workHourAccepted += max(0, (int) ($stats['accepted'] ?? 0));
        }

        if ($workHourTotal < $minSamples) {
            return false;
        }

        $workHourDeclined = max(0, $workHourTotal - $workHourAccepted);
        $workHourDeclineRate = $workHourDeclined / max(1, $workHourTotal);

        return $workHourDeclineRate >= $declineRateThreshold;
    }

    private function isWithinConfiguredWorkHours(int $hour): bool
    {
        $start = max(0, min(23, (int) config('services.notifications.work_hours_start', 8)));
        $end = max(1, min(24, (int) config('services.notifications.work_hours_end', 17)));

        return $hour >= $start && $hour < $end;
    }

    private function acceptanceProbabilityForHour(array $profile, int $hour): float
    {
        $overall = $profile['total'] > 0
            ? ($profile['accepted'] / $profile['total'])
            : 0.5;

        $stats = $profile['by_hour'][$hour] ?? ['total' => 0, 'accepted' => 0];
        $hourTotal = max(0, (int) $stats['total']);
        $hourAccepted = max(0, (int) $stats['accepted']);

        // Bayesian smoothing reduces volatility when hourly sample size is low.
        $alpha = 3;

        return ($hourAccepted + ($alpha * $overall)) / ($hourTotal + $alpha);
    }

    private function hourSelectExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', responded_at) AS INTEGER)"
            : 'HOUR(responded_at)';
    }
}
