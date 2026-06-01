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
            'normal_travel_radius' => 50,
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
            'normal_travel_radius' => 50,
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

    public function test_willing_donor_within_emergency_radius_is_included_in_emergency_matching(): void
    {
        $donor = $this->createDonor([
            'email' => 'emergency-willing@example.com',
            'name' => 'Emergency Willing Donor',
            'blood_type' => 'A+',
            'latitude' => 0.0000000,
            'longitude' => 0.5000000, // ~55km
            'availability' => true,
            'last_donation_date' => now()->subDays(90)->toDateString(),
            'reliability_score' => 80,
            'willing_for_emergency_travel' => true,
            'normal_travel_radius' => 5,
            'emergency_travel_radius' => 100,
        ]);

        $filter = app(DonorFilterService::class);

        $filtered = $filter->filterForRequest('A+', 0.0, 0.0, 200, null, null, true);

        $this->assertTrue(
            $filtered->pluck('donor.id')->contains($donor->id),
            'Donor willing for emergency travel within emergency radius should be included when emergency travel is permitted.'
        );
    }

    public function test_donor_with_matching_preferred_prc_chapter_is_ranked_higher(): void
    {
        $this->createDonor([
            'email' => 'chapter-pref@example.com',
            'name' => 'Preferred Chapter Donor',
            'blood_type' => 'A+',
            'latitude' => 0.0,
            'longitude' => 0.0100000,
            'availability' => true,
            'last_donation_date' => now()->subDays(90)->toDateString(),
            'reliability_score' => 75,
            'preferred_prc_chapter' => 'Manila Chapter',
        ]);

        $this->createDonor([
            'email' => 'no-pref@example.com',
            'name' => 'No Preference Donor',
            'blood_type' => 'A+',
            'latitude' => 0.0,
            'longitude' => 0.0100000,
            'availability' => true,
            'last_donation_date' => now()->subDays(90)->toDateString(),
            'reliability_score' => 75,
        ]);

        $filter = app(DonorFilterService::class);
        $pastMatch = app(PASTMatch::class);

        $filtered = $filter->filterForRequest('A+', 0.0, 0.0, 50);
        $ranked = $pastMatch->rankDonors($filtered, ['urgency_level' => 'medium', 'request_chapter_name' => 'Manila Chapter']);

        $this->assertSame('Preferred Chapter Donor', $ranked->first()['donor']->name);
        $this->assertGreaterThan($ranked[1]['score'], $ranked[0]['score']);
        $this->assertSame(100.0, $ranked->first()['factors']['chapter_preference']);
    }

    public function test_donor_beyond_normal_radius_is_included_with_lower_travel_willingness_when_emergency_travel_is_not_allowed(): void
    {
        $donor = $this->createDonor([
            'email' => 'normal-radius-boundary@example.com',
            'name' => 'Normal Radius Donor',
            'blood_type' => 'A+',
            'latitude' => 0.0000000,
            'longitude' => 0.0900900, // ~10km
            'availability' => true,
            'last_donation_date' => now()->subDays(90)->toDateString(),
            'reliability_score' => 80,
            'normal_travel_radius' => 5,
            'emergency_travel_radius' => 25,
        ]);

        $filter = app(DonorFilterService::class);
        $pastMatch = app(PASTMatch::class);

        $filtered = $filter->filterForRequest('A+', 0.0, 0.0, 50);
        $this->assertTrue(
            $filtered->pluck('donor.id')->contains($donor->id),
            'Donor outside normal travel radius should still be considered within the request radius when emergency travel is not enabled.'
        );

        $ranked = $pastMatch->rankDonors($filtered);
        $candidate = $ranked->firstWhere('donor.id', $donor->id);

        $this->assertNotNull($candidate);
        $this->assertLessThan(100.0, $candidate['factors']['travel_willingness']);
        $this->assertGreaterThanOrEqual(20.0, $candidate['factors']['travel_willingness']);
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
            'normal_travel_radius' => 50,
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
            'willing_for_emergency_travel' => (bool) ($attributes['willing_for_emergency_travel'] ?? false),
            'normal_travel_radius' => $attributes['normal_travel_radius'] ?? 5,
            'emergency_travel_radius' => $attributes['emergency_travel_radius'] ?? null,
            'preferred_prc_chapter' => $attributes['preferred_prc_chapter'] ?? null,
            'availability_status' => $attributes['availability_status'] ?? null,
            'privacy_consent_at' => now(),
        ]);
    }
}
