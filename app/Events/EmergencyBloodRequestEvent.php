<?php

namespace App\Events;

use App\Models\BloodRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Collection;

class EmergencyBloodRequestEvent
{
    use Dispatchable;

    /**
     * @param Collection<int, array{donor: \App\Models\Donor, score: float, factors: array<string, float>, distance_km: float|null, estimated_travel_minutes: float, traffic_condition: string, traffic_multiplier: float, transport_accessibility_score: float, fastest_arrival_score: float}> $rankedMatches
     */
    public function __construct(
        public BloodRequest $bloodRequest,
        public Collection $rankedMatches
    ) {
    }
}
