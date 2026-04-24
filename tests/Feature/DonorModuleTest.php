<?php

namespace Tests\Feature;

use App\Models\BloodRequest;
use App\Models\DonationHistory;
use App\Models\Donor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DonorModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_donor_registration_stores_donor_profile_and_consent_timestamp(): void
    {
        $response = $this->post('/register', [
            'name' => 'Donor One',
            'email' => 'donor1@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'blood_type' => 'A+',
            'city' => 'Abuja',
            'contact_number' => '08012345678',
            'last_donation_date' => '2026-01-10',
            'privacy_consent' => '1',
        ]);

        $response->assertRedirect(route('donor.dashboard', absolute: false));

        $this->assertDatabaseHas('donors', [
            'email' => 'donor1@example.com',
            'blood_type' => 'A+',
            'city' => 'Abuja',
            'availability' => 1,
        ]);

        $this->assertNotNull(Donor::where('email', 'donor1@example.com')->value('privacy_consent_at'));
    }

    public function test_donor_profile_can_be_updated(): void
    {
        $user = User::factory()->create(['role' => 'donor', 'name' => 'Old Name']);
        Donor::create([
            'user_id' => $user->id,
            'name' => 'Old Name',
            'blood_type' => 'B+',
            'city' => 'Kano',
            'contact_number' => '08011111111',
            'email' => $user->email,
            'password' => 'password',
            'availability' => true,
            'privacy_consent_at' => now(),
        ]);

        $response = $this->actingAs($user)->patch(route('donor.profile.update'), [
            'name' => 'New Name',
            'blood_type' => 'AB-',
            'city' => 'Ibadan',
            'contact_number' => '08022222222',
            'last_donation_date' => '2026-02-01',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('donors', [
            'user_id' => $user->id,
            'name' => 'New Name',
            'blood_type' => 'AB-',
            'city' => 'Ibadan',
            'contact_number' => '08022222222',
        ]);
    }

    public function test_donor_availability_can_be_toggled(): void
    {
        $user = User::factory()->create(['role' => 'donor']);
        Donor::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'blood_type' => 'O-',
            'city' => 'Lagos',
            'contact_number' => '08033333333',
            'email' => $user->email,
            'password' => 'password',
            'availability' => true,
            'privacy_consent_at' => now(),
        ]);

        $response = $this->actingAs($user)->patch(route('donor.availability.update'), [
            'availability' => 0,
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('donors', [
            'user_id' => $user->id,
            'availability' => 0,
        ]);
    }

    public function test_donor_dashboard_shows_route_assistance_with_map_navigation_and_eta(): void
    {
        $user = User::factory()->create(['role' => 'donor']);
        Donor::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'blood_type' => 'A+',
            'city' => 'Manila',
            'contact_number' => '08035555555',
            'phone' => '08035555555',
            'email' => $user->email,
            'password' => 'password',
            'availability' => true,
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'privacy_consent_at' => now(),
        ]);

        BloodRequest::create([
            'hospital_name' => 'Route Assist Hospital',
            'blood_type' => 'A+',
            'city' => 'Manila',
            'units_required' => 1,
            'quantity' => 1,
            'requested_units' => 1,
            'urgency_level' => 'high',
            'status' => 'matching',
            'latitude' => 14.6095,
            'longitude' => 120.9942,
        ]);

        $response = $this->actingAs($user)->get(route('donor.dashboard'));

        $response->assertOk();
        $response->assertSee('Route Assistance');
        $response->assertSee('Estimated Arrival');
        $response->assertSee('Open Navigation in Google Maps');
        $response->assertSee('google.com/maps/dir/?api=1', false);
        $response->assertSee('output=embed', false);
    }

    public function test_donor_dashboard_shows_achievement_progress_and_unlocked_milestones(): void
    {
        $user = User::factory()->create(['role' => 'donor']);

        $donor = Donor::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'blood_type' => 'O+',
            'city' => 'Abuja',
            'contact_number' => '08036666666',
            'email' => $user->email,
            'password' => 'password',
            'availability' => true,
            'privacy_consent_at' => now(),
        ]);

        for ($i = 1; $i <= 10; $i++) {
            DonationHistory::create([
                'donor_id' => $donor->id,
                'donated_at' => now()->subDays($i),
                'donation_date' => now()->subDays($i)->toDateString(),
                'location' => 'City Hospital',
                'units' => 1,
                'status' => 'completed',
            ]);
        }

        $response = $this->actingAs($user)->get(route('donor.dashboard'));

        $response->assertOk();
        $response->assertSee('Achievements &amp; Engagement', false);
        $response->assertSee('First Donation');
        $response->assertSee('5 Donations');
        $response->assertSee('10 Donations');
        $response->assertSee('Lifesaver Badge');
        $response->assertSee('3/4 unlocked');
        $response->assertSee('Only 10 more donation(s) to unlock Lifesaver Badge.');
    }
}

