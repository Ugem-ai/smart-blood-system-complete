<?php

namespace Tests\Unit;

use App\Algorithms\PASTMatch;
use App\Models\Donor;
use App\Models\User;
use App\Services\DonorFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PASTMatchAlgorithmValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_scenario_1_closest_donor_is_ranked_higher(): void
    {
        $this->createDonor([
            'email' => 'closest@example.com',
            'name' => 'Donor A',
            'blood_type' => 'A+',
            'latitude' => 0.0000000,
            'longitude' => 0.0090090, // ~1km from request
            'availability' => true,
            'last_donation_date' => now()->subDays(90)->toDateString(),
            'reliability_score' => 70,
        ]);

        $this->createDonor([
            'email' => 'farther@example.com',
            'name' => 'Donor B',
            'blood_type' => 'A+',
            'latitude' => 0.0000000,
            'longitude' => 0.0900900, // ~10km from request
            'availability' => true,
            'last_donation_date' => now()->subDays(90)->toDateString(),
            'reliability_score' => 70,
        ]);

        $filter = app(DonorFilterService::class);
        $pastMatch = app(PASTMatch::class);

        $filtered = $filter->filterForRequest('A+', 0.0, 0.0, 50);
        $ranked = $pastMatch->rankDonors($filtered);

        $this->assertSame('Donor A', $ranked->first()['donor']->name);
        $this->assertGreaterThan($ranked[1]['score'], $ranked[0]['score']);
    }

    public function test_scenario_2_donor_with_20_day_interval_is_filtered_out(): void
    {
        $recentDonor = $this->createDonor([
            'email' => 'recent-interval@example.com',
            'name' => 'Recent Interval Donor',
            'blood_type' => 'O+',
            'latitude' => 6.5244000,
            'longitude' => 3.3792000,
            'availability' => true,
            'last_donation_date' => now()->subDays(20)->toDateString(),
            'reliability_score' => 80,
        ]);

        $filter = app(DonorFilterService::class);

        $filtered = $filter->filterForRequest('O+', 6.5244, 3.3792, 50);

        $this->assertFalse(
            $filtered->pluck('donor.id')->contains($recentDonor->id),
            'Donor with last donation at 20 days should be excluded by 56-day minimum interval.'
        );
    }

    public function test_scenario_3_unavailable_donor_is_excluded_from_matching(): void
    {
        $unavailableDonor = $this->createDonor([
            'email' => 'unavailable@example.com',
            'name' => 'Unavailable Donor',
            'blood_type' => 'B+',
            'latitude' => 14.5995000,
            'longitude' => 120.9842000,
            'availability' => false,
            'last_donation_date' => now()->subDays(100)->toDateString(),
            'reliability_score' => 75,
        ]);

        $filter = app(DonorFilterService::class);

        $filtered = $filter->filterForRequest('B+', 14.5995, 120.9842, 50);

        $this->assertFalse(
            $filtered->pluck('donor.id')->contains($unavailableDonor->id),
            'Donor with availability=false must be excluded from matching.'
        );
    }

    public function test_scenario_4_reliability_score_adjusts_ranking(): void
    {
        // Reliability 0.8 interpreted as 80 in current scoring model (0..100).
        $this->createDonor([
            'email' => 'high-reliability@example.com',
            'name' => 'High Reliability Donor',
            'blood_type' => 'AB+',
            'latitude' => 10.0000000,
            'longitude' => 10.0450450, // ~5km
            'availability' => true,
            'last_donation_date' => now()->subDays(90)->toDateString(),
            'reliability_score' => 80,
        ]);

        $this->createDonor([
            'email' => 'low-reliability@example.com',
            'name' => 'Low Reliability Donor',
            'blood_type' => 'AB+',
            'latitude' => 10.0000000,
            'longitude' => 9.9549550, // ~5km opposite side
            'availability' => true,
            'last_donation_date' => now()->subDays(90)->toDateString(),
            'reliability_score' => 30,
        ]);

        $filter = app(DonorFilterService::class);
        $pastMatch = app(PASTMatch::class);

        $filtered = $filter->filterForRequest('AB+', 10.0, 10.0, 50);
        $ranked = $pastMatch->rankDonors($filtered);

        $this->assertSame('High Reliability Donor', $ranked->first()['donor']->name);
        $this->assertGreaterThan($ranked[1]['score'], $ranked[0]['score']);
    }

    public function test_same_city_donor_without_coordinates_uses_estimated_location_fallback(): void
    {
        $fallbackDonor = $this->createDonor([
            'email' => 'same-city-fallback@example.com',
            'name' => 'Same City Fallback Donor',
            'blood_type' => 'A+',
            'city' => 'Quezon City',
            'latitude' => null,
            'longitude' => null,
            'availability' => true,
            'last_donation_date' => now()->subDays(120)->toDateString(),
            'reliability_score' => 72,
        ]);

        $filter = app(DonorFilterService::class);

        $filtered = $filter->filterForRequest('A+', 14.676, 121.0437, 50, 'Quezon City');
        $candidate = $filtered->firstWhere('donor.id', $fallbackDonor->id);

        $this->assertNotNull($candidate);
        $this->assertSame('city-estimated', $candidate['location_source']);
        $this->assertSame(55.0, $candidate['location_confidence']);
        $this->assertNotNull($candidate['distance_km']);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createDonor(array $attributes): Donor
    {
        $email = (string) ($attributes['email'] ?? fake()->safeEmail());

        $user = User::factory()->create([
            'role' => 'donor',
            'email' => $email,
            'name' => (string) ($attributes['name'] ?? 'Donor User'),
        ]);

        return Donor::create([
            'user_id' => $user->id,
            'name' => (string) ($attributes['name'] ?? $user->name),
            'blood_type' => (string) ($attributes['blood_type'] ?? 'O+'),
            'city' => (string) ($attributes['city'] ?? 'Validation City'),
            'contact_number' => (string) ($attributes['contact_number'] ?? '09000000000'),
            'phone' => (string) ($attributes['phone'] ?? '09000000000'),
            'latitude' => $attributes['latitude'] ?? null,
            'longitude' => $attributes['longitude'] ?? null,
            'email' => $email,
            'password' => (string) ($attributes['password'] ?? 'password123'),
            'last_donation_date' => $attributes['last_donation_date'] ?? null,
            'availability' => (bool) ($attributes['availability'] ?? true),
            'reliability_score' => (float) ($attributes['reliability_score'] ?? 0),
            'privacy_consent_at' => now(),
        ]);
    }
}
