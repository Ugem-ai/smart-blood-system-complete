<?php

namespace Tests\Unit;

use App\Algorithms\PASTMatch;
use App\Models\Donor;
use App\Models\RequestMatch;
use App\Models\BloodRequest;
use App\Models\User;
use App\Models\Hospital;
use App\Services\EmergencyBroadcastModeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PASTMatchEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(EmergencyBroadcastModeService::class)->deactivate();

        parent::tearDown();
    }

    public function test_rank_donors_returns_empty_collection_when_no_candidates_are_available(): void
    {
        $ranked = app(PASTMatch::class)->rankDonors(collect());

        $this->assertInstanceOf(Collection::class, $ranked);
        $this->assertTrue($ranked->isEmpty());
    }

    public function test_emergency_priority_boost_can_reorder_candidates_with_faster_arrival(): void
    {
        config(['services.notifications.emergency_priority_boost_factor' => 0.75]);

        $baseReliable = $this->makeDonor('base-reliable@example.com', 'Base Reliable', 95);
        $fastArrival = $this->makeDonor('fast-arrival@example.com', 'Fast Arrival', 70);

        $candidates = collect([
            [
                'donor' => $baseReliable,
                'distance_km' => 1.0,
                'estimated_travel_minutes' => 90.0,
                'traffic_condition' => 'heavy',
                'traffic_multiplier' => 1.8,
                'transport_accessibility_score' => 70.0,
                'fastest_arrival_score' => 10.0,
            ],
            [
                'donor' => $fastArrival,
                'distance_km' => 40.0,
                'estimated_travel_minutes' => 70.0,
                'traffic_condition' => 'heavy',
                'traffic_multiplier' => 1.7,
                'transport_accessibility_score' => 20.0,
                'fastest_arrival_score' => 100.0,
            ],
        ]);

        $pastMatch = app(PASTMatch::class);

        $baselineRanked = $pastMatch->rankDonors($candidates);
        $baselineTop = $baselineRanked->first();
        app(EmergencyBroadcastModeService::class)->activate('earthquake', null);
        $emergencyRanked = $pastMatch->rankDonors($candidates);
        $emergencyTop = $emergencyRanked->first();

        $this->assertSame('Base Reliable', $baselineTop['donor']->name);
        $this->assertSame('Fast Arrival', $emergencyTop['donor']->name);
        $this->assertEquals($baselineTop['base_score'], $baselineTop['operational_score']);
        $this->assertSame(0.0, $baselineTop['emergency_adjustment']);
        $this->assertSame(0.0, $baselineTop['cooldown_penalty']);
        $this->assertGreaterThan(0, $emergencyTop['emergency_adjustment']);
        $this->assertEquals($emergencyTop['score'], $emergencyTop['operational_score']);
        $this->assertGreaterThan($emergencyTop['base_score'], $emergencyTop['operational_score']);
    }

    public function test_cooldown_penalty_reduces_operational_score_for_recently_matched_donor(): void
    {
        $recent = $this->makeDonor('recent@example.com', 'Recent Donor', 90);
        $fresh = $this->makeDonor('fresh@example.com', 'Fresh Donor', 90);

        // Both donors have identical scoring factors — without cooldown they would tie.
        $sharedItem = [
            'distance_km' => 10.0,
            'estimated_travel_minutes' => 20.0,
            'traffic_condition' => 'light',
            'traffic_multiplier' => 1.0,
            'transport_accessibility_score' => 80.0,
            'fastest_arrival_score' => 70.0,
            'location_source' => 'coordinates',
            'location_confidence' => 100.0,
        ];

        // Simulate recent donor having been matched 2 hours ago (< 6 h → -8 penalty).
        $candidates = collect([
            array_merge($sharedItem, ['donor' => $recent, 'last_matched_at' => now()->subHours(2)]),
            array_merge($sharedItem, ['donor' => $fresh,  'last_matched_at' => null]),
        ]);

        $ranked = app(PASTMatch::class)->rankDonors($candidates);

        $freshResult   = $ranked->firstWhere('donor.name', 'Fresh Donor');
        $recentResult  = $ranked->firstWhere('donor.name', 'Recent Donor');

        $this->assertSame(0.0, $freshResult['cooldown_penalty']);
        $this->assertSame(8.0, $recentResult['cooldown_penalty']);
        $this->assertEquals($freshResult['base_score'], $freshResult['operational_score']);
        $this->assertEquals($recentResult['base_score'] - 8.0, $recentResult['operational_score']);
        // Fresh donor should rank first because it has no penalty.
        $this->assertSame('Fresh Donor', $ranked->first()['donor']->name);
    }

    public function test_tie_breaking_by_donor_id_is_stable_and_reproducible(): void
    {
        $lower = $this->makeDonor('lower@example.com', 'Lower ID', 80);
        $higher = $this->makeDonor('higher@example.com', 'Higher ID', 80);

        // Ensure lower has the smaller ID (factory increments sequentially).
        $this->assertLessThan($higher->id, $lower->id);

        // Identical scoring profile for both donors — score tie guaranteed.
        $sharedItem = [
            'distance_km' => 15.0,
            'estimated_travel_minutes' => 30.0,
            'traffic_condition' => 'moderate',
            'traffic_multiplier' => 1.3,
            'transport_accessibility_score' => 60.0,
            'fastest_arrival_score' => 50.0,
            'location_source' => 'coordinates',
            'location_confidence' => 100.0,
            'last_matched_at' => null,
        ];

        $candidates = collect([
            array_merge($sharedItem, ['donor' => $higher]),
            array_merge($sharedItem, ['donor' => $lower]),
        ]);

        $ranked = app(PASTMatch::class)->rankDonors($candidates);

        // Donor with the lower (earlier) ID should be deterministically first.
        $this->assertSame($lower->id, $ranked->first()['donor']->id);
        $this->assertSame($higher->id, $ranked->last()['donor']->id);
    }

    private function makeDonor(string $email, string $name, float $reliability): Donor
    {
        $user = User::factory()->create([
            'role' => 'donor',
            'email' => $email,
            'name' => $name,
        ]);

        return Donor::create([
            'user_id' => $user->id,
            'name' => $name,
            'blood_type' => 'A+',
            'city' => 'Manila',
            'contact_number' => '09170000000',
            'phone' => '09170000000',
            'email' => $email,
            'password' => 'Password123!',
            'availability' => true,
            'reliability_score' => $reliability,
            'privacy_consent_at' => now(),
            'last_donation_date' => now()->subDays(120)->toDateString(),
        ]);
    }
}