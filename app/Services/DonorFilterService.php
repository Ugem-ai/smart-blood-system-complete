<?php

namespace App\Services;

use App\Models\Donor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

class DonorFilterService
{
    public const MIN_DONATION_INTERVAL_DAYS = 56;
    public const DEFAULT_DISTANCE_LIMIT_KM = 50;

    public function __construct(
        private readonly TravelIntelligenceService $travelIntelligenceService,
        private readonly DonorAllocationService $donorAllocationService
    )
    {
    }

    /**
     * Filter donors by blood compatibility, availability, donation interval,
     * and distance limit (Haversine) when request coordinates are available.
     *
    * @return Collection<int, array{donor: Donor, distance_km: float|null, estimated_travel_minutes: float, traffic_condition: string, traffic_multiplier: float, transport_accessibility_score: float, fastest_arrival_score: float}>
     */
    public function filterForRequest(
        string $requestedBloodType,
        ?float $requestLatitude,
        ?float $requestLongitude,
        ?int $distanceLimitKm = null,
        ?string $requestCity = null
    ): Collection {
        $distanceLimitKm ??= self::DEFAULT_DISTANCE_LIMIT_KM;

        $eligibleBloodTypes = $this->compatibleDonorTypes($requestedBloodType);

        $query = Donor::query()
            ->select([
                'id',
                'user_id',
                'name',
                'blood_type',
                'city',
                'contact_number',
                'phone',
                'latitude',
                'longitude',
                'email',
                'password',
                'last_donation_date',
                'availability',
                'reliability_score',
                'privacy_consent_at',
                'created_at',
                'updated_at',
            ])
            ->whereIn('blood_type', $eligibleBloodTypes)
            ->where('availability', true)
            ->where(function ($q) {
                $q->whereNull('last_donation_date')
                    ->orWhereDate('last_donation_date', '<=', now()->subDays(self::MIN_DONATION_INTERVAL_DAYS)->toDateString());
            });

        $reservedDonorIds = $this->donorAllocationService->reservedDonorIds();
        if ($reservedDonorIds !== []) {
            $query->whereNotIn('id', $reservedDonorIds);
        }

        if ($this->hasCoordinates($requestLatitude, $requestLongitude)) {
            [$minLat, $maxLat, $minLon, $maxLon] = $this->boundingBox(
                (float) $requestLatitude,
                (float) $requestLongitude,
                (float) $distanceLimitKm
            );

            $query->whereBetween('latitude', [$minLat, $maxLat])
                ->whereBetween('longitude', [$minLon, $maxLon]);
        }

        $donors = $query->get();

        $locationMapQuery = fn () => Donor::query()
            ->select(['id', 'latitude', 'longitude'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->keyBy('id')
            ->map(fn (Donor $d) => [
                'latitude' => $d->latitude !== null ? (float) $d->latitude : null,
                'longitude' => $d->longitude !== null ? (float) $d->longitude : null,
            ])
            ->all();

        // Test runs recycle auto-increment IDs across refreshed databases.
        // Bypass shared cache in tests to avoid stale coordinate maps.
        $locationCache = app()->environment('testing')
            ? $locationMapQuery()
            : Cache::remember('donors:locations:v1', now()->addMinutes(5), $locationMapQuery);

        return $donors
            ->filter(function (Donor $donor) use ($requestLatitude, $requestLongitude, $distanceLimitKm, $locationCache) {
                if (! $this->hasCoordinates($requestLatitude, $requestLongitude)) {
                    return true;
                }

                $cached = $locationCache[$donor->id] ?? null;
                $donorLat = $cached['latitude'] ?? null;
                $donorLon = $cached['longitude'] ?? null;

                if (! $this->hasCoordinates($donorLat, $donorLon)) {
                    return false;
                }

                $distance = $this->haversineDistanceKm(
                    (float) $requestLatitude,
                    (float) $requestLongitude,
                    (float) $donorLat,
                    (float) $donorLon
                );

                return $distance <= $distanceLimitKm;
            })
            ->map(function (Donor $donor) use ($requestLatitude, $requestLongitude, $locationCache, $requestCity) {
                $distance = null;

                $cached = $locationCache[$donor->id] ?? null;
                $donorLat = $cached['latitude'] ?? null;
                $donorLon = $cached['longitude'] ?? null;

                if ($this->hasCoordinates($requestLatitude, $requestLongitude)
                    && $this->hasCoordinates($donorLat, $donorLon)
                ) {
                    $distance = $this->haversineDistanceKm(
                        (float) $requestLatitude,
                        (float) $requestLongitude,
                        (float) $donorLat,
                        (float) $donorLon
                    );
                }

                $travel = $this->travelIntelligenceService->analyze(
                    distanceKm: $distance,
                    requestCity: $requestCity,
                    donorCity: $donor->city,
                    hasCoordinates: $this->hasCoordinates($donorLat, $donorLon)
                );

                return [
                    'donor' => $donor,
                    'distance_km' => $distance,
                    'estimated_travel_minutes' => $travel['estimated_travel_minutes'],
                    'traffic_condition' => $travel['traffic_condition'],
                    'traffic_multiplier' => $travel['traffic_multiplier'],
                    'transport_accessibility_score' => $travel['transport_accessibility_score'],
                    'fastest_arrival_score' => $travel['fastest_arrival_score'],
                ];
            })
            ->values();
    }

    /**
     * @return array<int, string>
     */
    public function compatibleDonorTypes(string $recipientType): array
    {
        $recipientType = strtoupper(trim($recipientType));

        $map = [
            'O-' => ['O-'],
            'O+' => ['O-', 'O+'],
            'A-' => ['O-', 'A-'],
            'A+' => ['O-', 'O+', 'A-', 'A+'],
            'B-' => ['O-', 'B-'],
            'B+' => ['O-', 'O+', 'B-', 'B+'],
            'AB-' => ['O-', 'A-', 'B-', 'AB-'],
            'AB+' => ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'],
        ];

        return $map[$recipientType] ?? [$recipientType];
    }

    public function isDonationIntervalEligible($lastDonationDate): bool
    {
        if (! $lastDonationDate) {
            return true;
        }

        return Carbon::parse($lastDonationDate)->diffInDays(now()) >= self::MIN_DONATION_INTERVAL_DAYS;
    }

    public function haversineDistanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
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

    protected function hasCoordinates(?float $latitude, ?float $longitude): bool
    {
        return $latitude !== null && $longitude !== null;
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    protected function boundingBox(float $latitude, float $longitude, float $radiusKm): array
    {
        $latDelta = $radiusKm / 111.0;
        $lonDelta = $radiusKm / max(1.0, 111.0 * cos(deg2rad($latitude)));

        return [
            $latitude - $latDelta,
            $latitude + $latDelta,
            $longitude - $lonDelta,
            $longitude + $lonDelta,
        ];
    }
}
