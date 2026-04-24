<?php

namespace App\Services;

use App\Models\Donor;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PastMatchService
{
    public const MIN_DONATION_GAP_DAYS = 56;

    /**
     * @var array<string, array<int, string>>
     */
    private const BLOOD_TYPE_COMPATIBILITY = [
        'O-' => ['O-'],
        'O+' => ['O-', 'O+'],
        'A-' => ['O-', 'A-'],
        'A+' => ['O-', 'O+', 'A-', 'A+'],
        'B-' => ['O-', 'B-'],
        'B+' => ['O-', 'O+', 'B-', 'B+'],
        'AB-' => ['O-', 'A-', 'B-', 'AB-'],
        'AB+' => ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'],
    ];

    /**
     * Match donors for a request profile and return ranked candidates.
     *
     * @return Collection<int, array{donor: Donor, score: float, distance_score: float}>
     */
    public function findTopDonors(string $bloodType, string $location, int $limit = 10): Collection
    {
        $eligibleBloodTypes = $this->compatibleDonorTypes($bloodType);
        $eligibilityCutoffDate = now()->subDays(self::MIN_DONATION_GAP_DAYS)->toDateString();
        $normalizedLocation = strtolower(trim($location));
        $locationScoreCache = [];

        $donors = Donor::query()
            ->whereIn('blood_type', $eligibleBloodTypes)
            ->where('availability', true)
            ->where(function ($query) use ($eligibilityCutoffDate) {
                $query->whereNull('last_donation_date')
                    ->orWhereDate('last_donation_date', '<=', $eligibilityCutoffDate);
            })
            ->get()
            ->filter(fn (Donor $donor) => $this->isDonationGapEligible($donor->last_donation_date));

        $ranked = $donors->map(function (Donor $donor) use ($bloodType, $normalizedLocation, &$locationScoreCache) {
            $donorLocation = strtolower(trim((string) $donor->city));

            if (! array_key_exists($donorLocation, $locationScoreCache)) {
                $locationScoreCache[$donorLocation] = $this->locationScore($normalizedLocation, $donorLocation);
            }

            $distanceScore = $locationScoreCache[$donorLocation];
            $score = $this->calculateMatchScore($bloodType, $donor, $distanceScore);

            return [
                'donor' => $donor,
                'score' => $score,
                'distance_score' => $distanceScore,
            ];
        })->sort(function (array $a, array $b) {
            if ($a['score'] === $b['score']) {
                return $b['distance_score'] <=> $a['distance_score'];
            }

            return $b['score'] <=> $a['score'];
        })->values();

        return $ranked->take($limit);
    }

    /**
     * @return array<int, string>
     */
    public function compatibleDonorTypes(string $recipientType): array
    {
        $recipientType = strtoupper(trim($recipientType));

        return self::BLOOD_TYPE_COMPATIBILITY[$recipientType] ?? [$recipientType];
    }

    public function calculateMatchScore(string $requestedBloodType, Donor $donor, float $distanceScore): float
    {
        $requestedBloodType = strtoupper(trim($requestedBloodType));
        $donorBloodType = strtoupper(trim((string) $donor->blood_type));

        // Blood type match: exact type preferred (60 pts) vs compatible-only (45 pts).
        $bloodScore = $donorBloodType === $requestedBloodType ? 60 : 45;

        // Donation gap freshness: longer rest since last donation is better.
        $lastDonationScore = 20;
        if ($donor->last_donation_date) {
            $days = Carbon::parse($donor->last_donation_date)->diffInDays(now());
            $lastDonationScore = $days >= 120 ? 20 : ($days >= 90 ? 15 : 10);
        }

        // Distance contributes up to 20 points.
        $distanceWeighted = $distanceScore * 20;

        // Reliability bonus: up to 20 extra points from the donor's reliability index.
        // A score of 100 → +20 pts; 50 (neutral) → +10 pts; 0 → 0 pts.
        $reliabilityBonus = ((float) ($donor->reliability_score ?? 50)) / 100 * 20;

        return round($bloodScore + $lastDonationScore + $distanceWeighted + $reliabilityBonus, 2);
    }

    protected function isDonationGapEligible($lastDonationDate): bool
    {
        if (! $lastDonationDate) {
            return true;
        }

        return Carbon::parse($lastDonationDate)->diffInDays(now()) >= self::MIN_DONATION_GAP_DAYS;
    }

    protected function locationScore(string $targetLocation, string $donorLocation): float
    {
        $target = strtolower(trim($targetLocation));
        $donor = strtolower(trim($donorLocation));

        if ($target === '' || $donor === '') {
            return 0.2;
        }

        if ($target === $donor) {
            return 1.0;
        }

        similar_text($target, $donor, $percent);

        return max(0.2, min(0.8, $percent / 100));
    }
}
