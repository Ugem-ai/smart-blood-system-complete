<?php

namespace App\Algorithms;

use App\Models\Donor;
use App\Services\EmergencyBroadcastModeService;
use App\Services\SystemSettingsService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PASTMatch
{
    public const MIN_DONATION_INTERVAL_DAYS = 56;
    public const DEFAULT_DISTANCE_LIMIT_KM = 50.0;
    public const DEFAULT_TRAVEL_SPEED_KMPH = 40.0;
    public const DEFAULT_MAX_TRAVEL_TIME_MINUTES = 120.0;

    public function __construct(
        private readonly EmergencyBroadcastModeService $emergencyBroadcastModeService,
        private readonly SystemSettingsService $systemSettingsService,
    ) {
    }

    /**
    * @param Collection<int, array{donor: Donor, distance_km: float|null, estimated_travel_minutes: float, traffic_condition: string, traffic_multiplier: float, transport_accessibility_score: float, fastest_arrival_score: float}> $filteredDonors
    * @return Collection<int, array{donor: Donor, score: float, base_score: float, emergency_adjustment: float, operational_score: float, factors: array<string, float>, distance_km: float|null, estimated_travel_minutes: float, traffic_condition: string, traffic_multiplier: float, transport_accessibility_score: float, fastest_arrival_score: float}>
     */
    public function rankDonors(Collection $filteredDonors, array $context = []): Collection
    {
        return $filteredDonors
            ->map(function (array $item) use ($context) {
                $donor = $item['donor'];
                $distanceKm = $item['distance_km'];
                $estimatedTravelMinutes = (float) ($item['estimated_travel_minutes'] ?? self::DEFAULT_MAX_TRAVEL_TIME_MINUTES);
                $trafficMultiplier = (float) ($item['traffic_multiplier'] ?? 1.5);
                $accessibilityScore = (float) ($item['transport_accessibility_score'] ?? 35.0);
                $arrivalScore = (float) ($item['fastest_arrival_score'] ?? 0.0);

                $factors = [
                    'proximity' => $this->calculateProximityScore($distanceKm),
                    'availability' => $this->calculateAvailabilityScore((bool) $donor->availability),
                    'donation_interval' => $this->calculateDonationIntervalScore($donor->last_donation_date),
                    'travel_time' => $this->calculateTravelTimeScore($estimatedTravelMinutes),
                    'reliability' => $this->calculateReliabilityScore($donor->reliability_score),
                    'traffic' => $this->calculateTrafficScore($trafficMultiplier),
                    'accessibility' => $this->calculateAccessibilityScore($accessibilityScore),
                    'arrival_priority' => $this->calculateArrivalPriorityScore($arrivalScore),
                ];

                $auditScores = $this->buildGroupedAuditScores($factors, $context['urgency_level'] ?? null);
                $baseScore = $auditScores['final'];
                $emergencyAdjustment = $this->emergencyBroadcastModeService->isActive()
                    ? $this->calculateEmergencyPriorityAdjustment($factors)
                    : 0.0;
                $cooldownPenalty = $this->calculateCooldownPenalty($item['last_matched_at'] ?? null);
                $operationalScore = round($baseScore + $emergencyAdjustment - $cooldownPenalty, 2);

                return [
                    'donor' => $donor,
                    'distance_km' => $distanceKm,
                    'estimated_travel_minutes' => $estimatedTravelMinutes,
                    'traffic_condition' => (string) ($item['traffic_condition'] ?? 'unknown'),
                    'traffic_multiplier' => $trafficMultiplier,
                    'transport_accessibility_score' => $accessibilityScore,
                    'fastest_arrival_score' => $arrivalScore,
                    'location_source' => (string) ($item['location_source'] ?? 'unknown'),
                    'location_confidence' => (float) ($item['location_confidence'] ?? 0.0),
                    'last_matched_at' => $item['last_matched_at'] ?? null,
                    'factors' => $factors,
                    'audit_scores' => $auditScores,
                    'base_score' => $baseScore,
                    'emergency_adjustment' => $emergencyAdjustment,
                    'cooldown_penalty' => $cooldownPenalty,
                    'operational_score' => $operationalScore,
                    'score' => $operationalScore,
                ];
            })
            ->sort(function (array $left, array $right): int {
                if ($left['operational_score'] !== $right['operational_score']) {
                    return $right['operational_score'] <=> $left['operational_score'];
                }

                if ($left['base_score'] !== $right['base_score']) {
                    return $right['base_score'] <=> $left['base_score'];
                }

                // Stable tertiary tiebreaker: earlier donor ID surfaces first for reproducibility.
                return $left['donor']->id <=> $right['donor']->id;
            })
            ->values();
    }

    public function calculateProximityScore(?float $distanceKm): float
    {
        if ($distanceKm === null) {
            return 50.0;
        }

        $normalized = max(0.0, 1.0 - ($distanceKm / self::DEFAULT_DISTANCE_LIMIT_KM));

        return round($normalized * 100, 2);
    }

    public function calculateAvailabilityScore(bool $isAvailable): float
    {
        return $isAvailable ? 100.0 : 0.0;
    }

    public function calculateDonationIntervalScore($lastDonationDate): float
    {
        if (! $lastDonationDate) {
            return 100.0;
        }

        $days = Carbon::parse($lastDonationDate)->diffInDays(now());

        if ($days >= self::MIN_DONATION_INTERVAL_DAYS) {
            return 100.0;
        }

        return round(($days / self::MIN_DONATION_INTERVAL_DAYS) * 100, 2);
    }

    public function calculateTravelTimeScore(?float $estimatedTravelMinutes): float
    {
        if ($estimatedTravelMinutes === null) {
            return 50.0;
        }

        $normalized = max(0.0, 1.0 - ($estimatedTravelMinutes / self::DEFAULT_MAX_TRAVEL_TIME_MINUTES));

        return round($normalized * 100, 2);
    }

    public function calculateTrafficScore(float $trafficMultiplier): float
    {
        return round(max(0.0, min(100.0, 100 - (($trafficMultiplier - 0.8) * 55))), 2);
    }

    public function calculateAccessibilityScore(float $accessibilityScore): float
    {
        return round(max(0.0, min(100.0, $accessibilityScore)), 2);
    }

    public function calculateArrivalPriorityScore(float $arrivalScore): float
    {
        return round(max(0.0, min(100.0, $arrivalScore)), 2);
    }

    public function calculateReliabilityScore($reliabilityScore): float
    {
        if ($reliabilityScore === null) {
            return 0.0;
        }

        return round(min(100.0, max(0.0, (float) $reliabilityScore)), 2);
    }

    /**
     * Base compatibility score is the weighted grouped audit score before emergency adjustment.
     *
     * @param array<string, float> $factors
     */
    public function computeFinalMatchScore(array $factors, ?string $urgencyLevel = null): float
    {
        return $this->buildGroupedAuditScores($factors, $urgencyLevel)['final'];
    }

    /**
     * @param array<string, float> $factors
     * @return array{priority: float, availability: float, distance: float, time: float, final: float, weights: array<string, float>}
     */
    public function buildGroupedAuditScores(array $factors, ?string $urgencyLevel = null): array
    {
        $urgencyPressure = match (strtolower(trim((string) ($urgencyLevel ?? 'medium')))) {
            'critical' => 100.0,
            'high' => 85.0,
            'low' => 45.0,
            default => 65.0,
        };

        $priority = round(($urgencyPressure * 0.55) + ((float) ($factors['arrival_priority'] ?? 0) * 0.25) + ((float) ($factors['donation_interval'] ?? 0) * 0.20), 2);
        $availability = round(
            ((float) ($factors['availability'] ?? 0) * 0.55) +
            ((float) ($factors['donation_interval'] ?? 0) * 0.25) +
            ((float) ($factors['reliability'] ?? 0) * 0.20),
            2
        );
        $distance = round(((float) ($factors['proximity'] ?? 0) * 0.70) + ((float) ($factors['accessibility'] ?? 0) * 0.30), 2);
        $time = round(((float) ($factors['travel_time'] ?? 0) * 0.55) + ((float) ($factors['arrival_priority'] ?? 0) * 0.25) + ((float) ($factors['traffic'] ?? 0) * 0.20), 2);
        $weights = $this->systemSettingsService->pastMatchWeights($urgencyLevel);

        $final = round(
            ($priority * $weights['priority']) +
            ($availability * $weights['availability']) +
            ($distance * $weights['distance']) +
            ($time * $weights['time']),
            2
        );

        return [
            'priority' => $priority,
            'availability' => $availability,
            'distance' => $distance,
            'time' => $time,
            'final' => $final,
            'weights' => $weights,
        ];
    }

    /**
     * Fairness rotation: donors matched recently receive a small operational penalty
     * so that high-reliability donors do not monopolise every request queue.
     * The penalty affects only operational_score; the base audit score is unchanged.
     */
    private function calculateCooldownPenalty($lastMatchedAt): float
    {
        if ($lastMatchedAt === null) {
            return 0.0;
        }

        $hoursAgo = Carbon::parse($lastMatchedAt)->diffInHours(now());

        return match (true) {
            $hoursAgo < 6  => 8.0,
            $hoursAgo < 24 => 5.0,
            $hoursAgo < 72 => 2.0,
            default        => 0.0,
        };
    }

    /**
     * @param array<string, float> $factors
     */
    private function calculateEmergencyPriorityAdjustment(array $factors): float
    {
        $prioritySignal =
            ($factors['arrival_priority'] * 0.40) +
            ($factors['travel_time'] * 0.25) +
            ($factors['proximity'] * 0.20) +
            ($factors['reliability'] * 0.15);

        $boost = $prioritySignal * $this->emergencyBroadcastModeService->emergencyPriorityBoostFactor();

        return round($boost, 2);
    }
}
