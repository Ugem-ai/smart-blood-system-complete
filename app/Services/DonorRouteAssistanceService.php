<?php

namespace App\Services;

class DonorRouteAssistanceService
{
    public function __construct(private readonly TravelIntelligenceService $travelIntelligenceService)
    {
    }

    /**
     * @return array{
     *   distance_km: float|null,
     *   estimated_arrival_minutes: float,
     *   traffic_condition: string,
     *   navigation_url: string,
     *   map_embed_url: string
     * }
     */
    public function assistanceForRequest(
        ?float $donorLatitude,
        ?float $donorLongitude,
        ?float $hospitalLatitude,
        ?float $hospitalLongitude,
        ?string $donorCity,
        ?string $hospitalCity,
        ?string $hospitalName
    ): array {
        $distanceKm = null;
        $hasCoordinates = $this->hasCoordinates($donorLatitude, $donorLongitude)
            && $this->hasCoordinates($hospitalLatitude, $hospitalLongitude);

        if ($hasCoordinates) {
            $distanceKm = $this->haversineDistanceKm(
                (float) $donorLatitude,
                (float) $donorLongitude,
                (float) $hospitalLatitude,
                (float) $hospitalLongitude
            );
        }

        $travel = $this->travelIntelligenceService->analyze(
            distanceKm: $distanceKm,
            requestCity: $hospitalCity,
            donorCity: $donorCity,
            hasCoordinates: $hasCoordinates
        );

        return [
            'distance_km' => $distanceKm,
            'estimated_arrival_minutes' => $travel['estimated_travel_minutes'],
            'traffic_condition' => $travel['traffic_condition'],
            'navigation_url' => $this->googleNavigationUrl(
                $donorLatitude,
                $donorLongitude,
                $hospitalLatitude,
                $hospitalLongitude,
                $hospitalName,
                $hospitalCity
            ),
            'map_embed_url' => $this->googleMapEmbedUrl(
                $hospitalLatitude,
                $hospitalLongitude,
                $hospitalName,
                $hospitalCity
            ),
        ];
    }

    private function googleNavigationUrl(
        ?float $donorLatitude,
        ?float $donorLongitude,
        ?float $hospitalLatitude,
        ?float $hospitalLongitude,
        ?string $hospitalName,
        ?string $hospitalCity
    ): string {
        if ($this->hasCoordinates($hospitalLatitude, $hospitalLongitude)) {
            $destination = $hospitalLatitude.','.$hospitalLongitude;

            $query = [
                'api' => '1',
                'destination' => $destination,
                'travelmode' => 'driving',
            ];

            if ($this->hasCoordinates($donorLatitude, $donorLongitude)) {
                $query['origin'] = $donorLatitude.','.$donorLongitude;
            }

            return 'https://www.google.com/maps/dir/?'.http_build_query($query);
        }

        return 'https://www.google.com/maps/dir/?'.http_build_query([
            'api' => '1',
            'destination' => trim((string) $hospitalName.' '.(string) $hospitalCity),
            'travelmode' => 'driving',
        ]);
    }

    private function googleMapEmbedUrl(
        ?float $hospitalLatitude,
        ?float $hospitalLongitude,
        ?string $hospitalName,
        ?string $hospitalCity
    ): string {
        if ($this->hasCoordinates($hospitalLatitude, $hospitalLongitude)) {
            return 'https://www.google.com/maps?q='.(string) $hospitalLatitude.','.(string) $hospitalLongitude.'&z=15&output=embed';
        }

        return 'https://www.google.com/maps?q='.urlencode(trim((string) $hospitalName.' '.(string) $hospitalCity)).'&output=embed';
    }

    private function hasCoordinates(?float $latitude, ?float $longitude): bool
    {
        return $latitude !== null && $longitude !== null;
    }

    private function haversineDistanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadiusKm * $c, 2);
    }
}