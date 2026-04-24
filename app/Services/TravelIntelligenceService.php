<?php

namespace App\Services;

class TravelIntelligenceService
{
    public const DEFAULT_BASE_SPEED_KMPH = 40.0;
    public const MAX_TARGET_TRAVEL_MINUTES = 120.0;

    public function estimateDistanceFromCityContext(?string $requestCity, ?string $donorCity, float $distanceLimitKm): ?float
    {
        $normalizedRequestCity = $this->normalize($requestCity);
        $normalizedDonorCity = $this->normalize($donorCity);

        if ($normalizedRequestCity === '' || $normalizedDonorCity === '') {
            return null;
        }

        if ($normalizedRequestCity !== $normalizedDonorCity) {
            return null;
        }

        return round(min(max(8.0, $distanceLimitKm * 0.28), 18.0), 2);
    }

    /**
     * @return array{
     *   estimated_travel_minutes: float,
     *   traffic_condition: string,
     *   traffic_multiplier: float,
     *   transport_accessibility_score: float,
     *   fastest_arrival_score: float
     * }
     */
    public function analyze(
        ?float $distanceKm,
        ?string $requestCity,
        ?string $donorCity,
        bool $hasCoordinates
    ): array {
        if ($distanceKm === null) {
            return [
                'estimated_travel_minutes' => self::MAX_TARGET_TRAVEL_MINUTES,
                'traffic_condition' => 'unknown',
                'traffic_multiplier' => 1.5,
                'transport_accessibility_score' => 35.0,
                'fastest_arrival_score' => 10.0,
            ];
        }

        $cityMatch = $this->normalize($requestCity) !== ''
            && $this->normalize($requestCity) === $this->normalize($donorCity);

        $trafficMultiplier = $this->trafficMultiplier($distanceKm, $cityMatch);
        $trafficCondition = $this->trafficCondition($trafficMultiplier);
        $accessibilityScore = $this->transportAccessibilityScore($distanceKm, $cityMatch, $hasCoordinates);

        $baseTravelMinutes = ($distanceKm / self::DEFAULT_BASE_SPEED_KMPH) * 60;
        $accessibilityMultiplier = 1.25 - (($accessibilityScore / 100) * 0.45);
        $estimatedTravelMinutes = max(1.0, $baseTravelMinutes * $trafficMultiplier * $accessibilityMultiplier);

        $arrivalNormalized = max(0.0, 1.0 - ($estimatedTravelMinutes / self::MAX_TARGET_TRAVEL_MINUTES));

        return [
            'estimated_travel_minutes' => round($estimatedTravelMinutes, 2),
            'traffic_condition' => $trafficCondition,
            'traffic_multiplier' => round($trafficMultiplier, 2),
            'transport_accessibility_score' => round($accessibilityScore, 2),
            'fastest_arrival_score' => round($arrivalNormalized * 100, 2),
        ];
    }

    private function trafficMultiplier(float $distanceKm, bool $cityMatch): float
    {
        $hour = (int) now()->format('G');

        $base = match (true) {
            $hour >= 7 && $hour <= 9 => 1.55,
            $hour >= 17 && $hour <= 20 => 1.65,
            $hour >= 10 && $hour <= 16 => 1.2,
            $hour >= 0 && $hour <= 5 => 0.9,
            default => 1.05,
        };

        if (! $cityMatch) {
            $base += 0.55;
        }

        // Dense inner-city trips often suffer extra congestion even at short distance.
        if ($distanceKm <= 5) {
            $base += 0.15;
        }

        return min(2.5, max(0.8, $base));
    }

    private function trafficCondition(float $trafficMultiplier): string
    {
        return match (true) {
            $trafficMultiplier >= 2.0 => 'heavy',
            $trafficMultiplier >= 1.35 => 'moderate',
            default => 'light',
        };
    }

    private function transportAccessibilityScore(float $distanceKm, bool $cityMatch, bool $hasCoordinates): float
    {
        $score = $hasCoordinates ? 75.0 : 40.0;
        $score += $cityMatch ? 15.0 : -10.0;

        if ($distanceKm <= 10) {
            $score += 10.0;
        } elseif ($distanceKm <= 25) {
            $score += 5.0;
        } else {
            $score -= 10.0;
        }

        return min(100.0, max(20.0, $score));
    }

    private function normalize(?string $value): string
    {
        return strtolower(trim((string) $value));
    }
}
