<?php

namespace App\Algorithms;

use App\Models\Donor;
use App\Models\DonorRequestAcceptance;
use App\Services\EmergencyBroadcastModeService;
use App\Services\SystemSettingsService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
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
        $now = now();
        $weights = $this->systemSettingsService->pastMatchWeights($context['urgency_level'] ?? null);
        $acceptedDonorIdsForRequest = collect();

        $bloodRequestId = isset($context['blood_request_id'])
            ? (int) $context['blood_request_id']
            : null;

        if ($bloodRequestId) {
            $candidateDonorIds = $filteredDonors
                ->pluck('donor.id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            if ($candidateDonorIds !== []) {
                $acceptedDonorIdsForRequest = DonorRequestAcceptance::query()
                    ->where('blood_request_id', $bloodRequestId)
                    ->where('status', 'accepted')
                    ->whereIn('donor_id', $candidateDonorIds)
                    ->pluck('donor_id')
                    ->map(fn ($id) => (int) $id);
            }
        }

        return $filteredDonors
            ->map(function (array $item) use ($context, $now, $weights, $acceptedDonorIdsForRequest) {
                $donor = $item['donor'];
                $distanceKm = $item['distance_km'];
                $estimatedTravelMinutes = (float) ($item['estimated_travel_minutes'] ?? self::DEFAULT_MAX_TRAVEL_TIME_MINUTES);
                $trafficMultiplier = (float) ($item['traffic_multiplier'] ?? 1.5);
                $accessibilityScore = (float) ($item['transport_accessibility_score'] ?? 35.0);
                $arrivalScore = (float) ($item['fastest_arrival_score'] ?? 0.0);

                $proximityScore = $this->calculateProximityScore($distanceKm);
                $availabilityScore = $this->calculateAvailabilityScore((bool) $donor->availability);
                $donationIntervalScore = $this->calculateDonationIntervalScore($donor->last_donation_date, $now);
                $travelTimeScore = $this->calculateTravelTimeScore($estimatedTravelMinutes);
                $reliabilityScore = $this->calculateReliabilityScore($donor->reliability_score);
                $trafficScore = $this->calculateTrafficScore($trafficMultiplier);
                $accessibilityScoreCalc = $this->calculateAccessibilityScore($accessibilityScore);
                $arrivalPriorityScore = $this->calculateArrivalPriorityScore($arrivalScore);

                $requestChapterName = isset($context['request_chapter_name']) ? trim((string) $context['request_chapter_name']) : null;
                $requestChapterCode = isset($context['request_chapter_code']) ? trim((string) $context['request_chapter_code']) : null;
                $chapterPreferenceScore = $this->calculateChapterPreferenceScore($donor, $requestChapterName, $requestChapterCode);

                // New factors: travel_willingness, distance_efficiency, and chapter_preference
                $travelWillingness = 0.0;
                if ($acceptedDonorIdsForRequest->contains((int) $donor->id)) {
                    $travelWillingness = 100.0;
                } elseif ($donor->willing_for_emergency_travel ?? false) {
                    $travelWillingness = 100.0;
                } elseif ($distanceKm !== null) {
                    $normalRadius = max(5, (int) ($donor->normal_travel_radius ?? 5));
                    if ($distanceKm <= $normalRadius || $estimatedTravelMinutes <= 15.0) {
                        $travelWillingness = 100.0;
                    } else {
                        $travelWillingness = round(max(20.0, 100.0 - (($distanceKm - $normalRadius) * 4.0)), 2);
                    }
                }

                $distanceEfficiency = round((($proximityScore * 0.35) + ($travelTimeScore * 0.25) + ($arrivalPriorityScore * 0.20) + ($accessibilityScoreCalc * 0.20)), 2);

                $factors = [
                    'compatibility' => round((($donationIntervalScore * 0.6) + ($reliabilityScore * 0.4)), 2),
                    'availability' => $availabilityScore,
                    'travel_willingness' => $travelWillingness,
                    'distance_efficiency' => $distanceEfficiency,
                    'chapter_preference' => $chapterPreferenceScore,
                    'proximity' => $proximityScore,
                    'donation_interval' => $donationIntervalScore,
                    'travel_time' => $travelTimeScore,
                    'reliability' => $reliabilityScore,
                    'traffic' => $trafficScore,
                    'accessibility' => $accessibilityScoreCalc,
                    'arrival_priority' => $arrivalPriorityScore,
                ];

                $auditScores = $this->buildGroupedAuditScores($factors, $weights, $context['urgency_level'] ?? null);
                $baseScore = $auditScores['final'];
                $emergencyAdjustment = $this->emergencyBroadcastModeService->isActive()
                    ? $this->calculateEmergencyPriorityAdjustment($factors)
                    : 0.0;
                $cooldownPenalty = $this->calculateCooldownPenalty($item['last_matched_at'] ?? null, $now);
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

    public function calculateDonationIntervalScore($lastDonationDate, ?CarbonInterface $now = null): float
    {
        $now ??= now();

        if (! $lastDonationDate) {
            return 100.0;
        }

        $lastDonationDate = $lastDonationDate instanceof CarbonInterface
            ? $lastDonationDate
            : Carbon::parse($lastDonationDate);

        $days = $lastDonationDate->diffInDays($now);

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
     * @return array{compatibility: float, availability: float, travel_willingness: float, distance_efficiency: float, chapter_preference: float, final: float, weights: array<string, float>}
     */
    public function buildGroupedAuditScores(array $factors, ?array $weights = null, ?string $urgencyLevel = null): array
    {
        $weights ??= $this->systemSettingsService->pastMatchWeights($urgencyLevel);

        $compatibility = round((float) ($factors['compatibility'] ?? 0), 2);
        $availability = round((float) ($factors['availability'] ?? 0), 2);
        $travelWillingness = round((float) ($factors['travel_willingness'] ?? 0), 2);
        $distanceEfficiency = round((float) ($factors['distance_efficiency'] ?? 0), 2);
        $chapterPreference = round((float) ($factors['chapter_preference'] ?? 0), 2);

        $final = round(
            ($compatibility * ($weights['compatibility'] ?? 0.5)) +
            ($availability * ($weights['availability'] ?? 0.1)) +
            ($travelWillingness * ($weights['travel_willingness'] ?? 0.15)) +
            ($distanceEfficiency * ($weights['distance_efficiency'] ?? 0.25)) +
            ($chapterPreference * ($weights['chapter_preference'] ?? 0.05)),
            2
        );

        // Legacy grouped scores (kept for backward compatibility with UI/tests)
        $urgencyPressure = match (strtolower(trim((string) ($urgencyLevel ?? 'medium')))) {
            'critical' => 100.0,
            'high' => 85.0,
            'low' => 45.0,
            default => 65.0,
        };

        $priorityLegacy = round(($urgencyPressure * 0.55) + ((float) ($factors['arrival_priority'] ?? 0) * 0.25) + ((float) ($factors['donation_interval'] ?? 0) * 0.20), 2);
        $availabilityLegacy = round(
            ((float) ($factors['availability'] ?? 0) * 0.55) +
            ((float) ($factors['donation_interval'] ?? 0) * 0.25) +
            ((float) ($factors['reliability'] ?? 0) * 0.20),
            2
        );
        $distanceLegacy = round(((float) ($factors['proximity'] ?? 0) * 0.70) + ((float) ($factors['accessibility'] ?? 0) * 0.30), 2);
        $timeLegacy = round(((float) ($factors['travel_time'] ?? 0) * 0.55) + ((float) ($factors['arrival_priority'] ?? 0) * 0.25) + ((float) ($factors['traffic'] ?? 0) * 0.20), 2);

        $chapterPreference = round((float) ($factors['chapter_preference'] ?? 0), 2);

        $final = round(
            ($compatibility * ($weights['compatibility'] ?? 0.5)) +
            ($availability * ($weights['availability'] ?? 0.1)) +
            ($travelWillingness * ($weights['travel_willingness'] ?? 0.15)) +
            ($distanceEfficiency * ($weights['distance_efficiency'] ?? 0.25)) +
            ($chapterPreference * ($weights['chapter_preference'] ?? 0.05)),
            2
        );

        return [
            'compatibility' => $compatibility,
            'availability' => $availability,
            'travel_willingness' => $travelWillingness,
            'distance_efficiency' => $distanceEfficiency,
            'chapter_preference' => $chapterPreference,
            'final' => $final,
            'weights' => $weights,
            // Legacy fields below
            'priority' => $priorityLegacy,
            'availability_legacy' => $availabilityLegacy,
            'distance' => $distanceLegacy,
            'time' => $timeLegacy,
        ];
    }

    /**
     * Fairness rotation: donors matched recently receive a small operational penalty
     * so that high-reliability donors do not monopolise every request queue.
     * The penalty affects only operational_score; the base audit score is unchanged.
     */
    private function calculateCooldownPenalty($lastMatchedAt, ?CarbonInterface $now = null): float
    {
        if ($lastMatchedAt === null) {
            return 0.0;
        }

        $now ??= now();
        $lastMatchedAt = $lastMatchedAt instanceof CarbonInterface
            ? $lastMatchedAt
            : Carbon::parse($lastMatchedAt);

        $hoursAgo = $lastMatchedAt->diffInHours($now);

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
            ($factors['arrival_priority'] * 0.55) +
            ($factors['travel_time'] * 0.30) +
            ($factors['proximity'] * 0.10) +
            ($factors['reliability'] * 0.05);

        $boost = $prioritySignal * $this->emergencyBroadcastModeService->emergencyPriorityBoostFactor();

        return round($boost, 2);
    }

    private function calculateChapterPreferenceScore(Donor $donor, ?string $requestChapterName, ?string $requestChapterCode): float
    {
        $preference = trim((string) ($donor->preferred_prc_chapter ?? ''));

        if ($preference === '') {
            return 0.0;
        }

        $normalizedPreference = strtolower($preference);

        if ($requestChapterName !== null && $normalizedPreference === strtolower(trim($requestChapterName))) {
            return 100.0;
        }

        if ($requestChapterCode !== null && $normalizedPreference === strtolower(trim($requestChapterCode))) {
            return 100.0;
        }

        return 0.0;
    }
}
