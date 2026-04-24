<?php

namespace Tests\Unit;

use App\Models\Donor;
use App\Models\User;
use App\Services\PastMatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PastMatchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_algorithm_filters_incompatible_unavailable_and_recent_donors(): void
    {
        $service = new PastMatchService();

        $eligibleUser = User::factory()->create(['role' => 'donor', 'email' => 'eligible@example.com']);
        Donor::create([
            'user_id' => $eligibleUser->id,
            'name' => 'Eligible Donor',
            'blood_type' => 'O-',
            'city' => 'Lagos',
            'contact_number' => '08010000000',
            'email' => $eligibleUser->email,
            'password' => 'password',
            'last_donation_date' => now()->subDays(80)->toDateString(),
            'availability' => true,
            'privacy_consent_at' => now(),
        ]);

        $unavailableUser = User::factory()->create(['role' => 'donor', 'email' => 'unavailable@example.com']);
        Donor::create([
            'user_id' => $unavailableUser->id,
            'name' => 'Unavailable Donor',
            'blood_type' => 'O-',
            'city' => 'Lagos',
            'contact_number' => '08010000001',
            'email' => $unavailableUser->email,
            'password' => 'password',
            'last_donation_date' => now()->subDays(90)->toDateString(),
            'availability' => false,
            'privacy_consent_at' => now(),
        ]);

        $recentUser = User::factory()->create(['role' => 'donor', 'email' => 'recent@example.com']);
        Donor::create([
            'user_id' => $recentUser->id,
            'name' => 'Recent Donor',
            'blood_type' => 'O-',
            'city' => 'Lagos',
            'contact_number' => '08010000002',
            'email' => $recentUser->email,
            'password' => 'password',
            'last_donation_date' => now()->subDays(20)->toDateString(),
            'availability' => true,
            'privacy_consent_at' => now(),
        ]);

        $incompatibleUser = User::factory()->create(['role' => 'donor', 'email' => 'incompatible@example.com']);
        Donor::create([
            'user_id' => $incompatibleUser->id,
            'name' => 'Incompatible Donor',
            'blood_type' => 'AB+',
            'city' => 'Lagos',
            'contact_number' => '08010000003',
            'email' => $incompatibleUser->email,
            'password' => 'password',
            'last_donation_date' => now()->subDays(100)->toDateString(),
            'availability' => true,
            'privacy_consent_at' => now(),
        ]);

        $matches = $service->findTopDonors('A+', 'Lagos', 10);

        $this->assertCount(1, $matches);
        $this->assertSame('Eligible Donor', $matches->first()['donor']->name);
    }

    public function test_algorithm_ranks_by_score_and_location_correctly(): void
    {
        $service = new PastMatchService();

        $exactUser = User::factory()->create(['role' => 'donor', 'email' => 'exact@example.com']);
        Donor::create([
            'user_id' => $exactUser->id,
            'name' => 'Exact Match',
            'blood_type' => 'A+',
            'city' => 'Abuja',
            'contact_number' => '08020000000',
            'email' => $exactUser->email,
            'password' => 'password',
            'last_donation_date' => now()->subDays(130)->toDateString(),
            'availability' => true,
            'privacy_consent_at' => now(),
        ]);

        $compatibleUser = User::factory()->create(['role' => 'donor', 'email' => 'compatible@example.com']);
        Donor::create([
            'user_id' => $compatibleUser->id,
            'name' => 'Compatible Match',
            'blood_type' => 'O+',
            'city' => 'Kano',
            'contact_number' => '08020000001',
            'email' => $compatibleUser->email,
            'password' => 'password',
            'last_donation_date' => now()->subDays(100)->toDateString(),
            'availability' => true,
            'privacy_consent_at' => now(),
        ]);

        $matches = $service->findTopDonors('A+', 'Abuja', 10);

        $this->assertGreaterThanOrEqual(2, $matches->count());
        $this->assertSame('Exact Match', $matches[0]['donor']->name);
        $this->assertGreaterThan($matches[1]['score'], $matches[0]['score']);
    }
}
