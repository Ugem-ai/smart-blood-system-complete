<?php

namespace Tests\Feature;

use App\Algorithms\PASTMatch;
use App\Models\Donor;
use App\Models\User;
use App\Services\DonorFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeoAwareSmartMatchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_matching_prioritizes_fastest_arrival_not_just_shortest_distance(): void
    {
        $closerButSlower = $this->makeDonor([
            'name' => 'Closer But Slower',
            'city' => 'Quezon City',
            'latitude' => 14.6395,
            'longitude' => 120.9842,
            'reliability_score' => 70,
        ]);

        $fartherButFaster = $this->makeDonor([
            'name' => 'Farther But Faster',
            'city' => 'Manila',
            'latitude' => 14.6715,
            'longitude' => 120.9842,
            'reliability_score' => 70,
        ]);

        $filter = app(DonorFilterService::class);
        $algorithm = app(PASTMatch::class);

        $filtered = $filter->filterForRequest(
            requestedBloodType: 'A+',
            requestLatitude: 14.5995,
            requestLongitude: 120.9842,
            distanceLimitKm: 25,
            requestCity: 'Manila',
        );

        $ranked = $algorithm->rankDonors($filtered)->values();

        $top = $ranked->first();
        $closer = $ranked->firstWhere('donor.id', $closerButSlower->id);
        $faster = $ranked->firstWhere('donor.id', $fartherButFaster->id);

        $this->assertNotNull($top);
        $this->assertSame($fartherButFaster->id, $top['donor']->id);
        $this->assertTrue(
            $faster['estimated_travel_minutes'] < $closer['estimated_travel_minutes']
                || $faster['traffic_multiplier'] < $closer['traffic_multiplier'],
            'Expected farther donor to demonstrate better travel dynamics (lower ETA or better traffic multiplier).'
        );
        $this->assertTrue(
            $faster['score'] > $closer['score'],
            'Expected farther donor to be ranked higher once traffic/accessibility factors are applied.'
        );
    }

    private function makeDonor(array $overrides = []): Donor
    {
        $user = User::factory()->create(['role' => 'donor']);

        return Donor::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Geo Donor '.$user->id,
            'blood_type' => 'A+',
            'city' => 'Manila',
            'contact_number' => '09170000111',
            'email' => $user->email,
            'password' => 'Password123!',
            'availability' => true,
            'privacy_consent_at' => now(),
            'reliability_score' => 70,
            'latitude' => 14.5995,
            'longitude' => 120.9842,
        ], $overrides));
    }
}

