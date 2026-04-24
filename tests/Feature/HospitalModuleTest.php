<?php

namespace Tests\Feature;

use App\Models\BloodRequest;
use App\Models\Donor;
use App\Models\RequestMatch;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HospitalModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_hospital_registration_creates_pending_hospital_profile(): void
    {
        $response = $this->post(route('register.hospital.store'), [
            'hospital_name' => 'General Hospital',
            'location' => 'Lagos',
            'contact_person' => 'Dr. Jane',
            'contact_number' => '08050000000',
            'email' => 'general@example.com',
            'hospital_registration_code' => 'test-hospital-code',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('login', absolute: false));

        $this->assertDatabaseHas('hospitals', [
            'hospital_name' => 'General Hospital',
            'status' => 'pending',
            'email' => 'general@example.com',
        ]);
    }

    public function test_approved_hospital_can_submit_blood_request(): void
    {
        $user = User::factory()->create([
            'role' => 'hospital',
            'email' => 'hospital1@example.com',
        ]);

        $hospital = Hospital::create([
            'user_id' => $user->id,
            'hospital_name' => 'LifeCare',
            'location' => 'Lagos',
            'contact_person' => 'Dr. Mike',
            'contact_number' => '08061111111',
            'email' => $user->email,
            'password' => 'password',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($user)->post(route('hospital.requests.submit'), [
            'blood_type' => 'O+',
            'city' => 'Lagos',
            'requested_units' => 3,
            'required_on' => '2026-03-20',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('blood_requests', [
            'hospital_id' => $hospital->id,
            'blood_type' => 'O+',
            'city' => 'Lagos',
            'requested_units' => 3,
            'quantity' => 3,
            'status' => 'pending',
        ]);
    }

    public function test_hospital_cannot_login_until_admin_approval_then_can_login_after_approval(): void
    {
        $user = User::factory()->create([
            'role' => 'hospital',
            'email' => 'pending@example.com',
        ]);

        $hospital = Hospital::create([
            'user_id' => $user->id,
            'hospital_name' => 'Pending Hospital',
            'location' => 'Ibadan',
            'contact_person' => 'Dr. Pending',
            'contact_number' => '08072222222',
            'email' => $user->email,
            'password' => 'password',
            'status' => 'pending',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->patch(route('admin.hospitals.approve', $hospital))->assertRedirect();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->actingAs($user)
            ->get(route('dashboard', absolute: false))
            ->assertRedirect(route('hospital.dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_hospital_can_view_dedicated_matched_donors_page_with_coordination_details(): void
    {
        $user = User::factory()->create([
            'role' => 'hospital',
            'email' => 'hospital-matches@example.com',
        ]);

        $hospital = Hospital::create([
            'user_id' => $user->id,
            'hospital_name' => 'Matcher Hospital',
            'address' => 'Matcher Address',
            'location' => 'Manila',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'contact_person' => 'Dr Matcher',
            'contact_number' => '08063333333',
            'email' => $user->email,
            'password' => 'password',
            'status' => 'approved',
        ]);

        $donorUser = User::factory()->create(['role' => 'donor']);
        $donor = Donor::create([
            'user_id' => $donorUser->id,
            'name' => 'Reserved Elsewhere Donor',
            'blood_type' => 'O+',
            'city' => 'Manila',
            'contact_number' => '08064444444',
            'phone' => '08064444444',
            'email' => $donorUser->email,
            'password' => 'password',
            'latitude' => 14.6095,
            'longitude' => 120.9842,
            'availability' => true,
            'reliability_score' => 85,
            'privacy_consent_at' => now(),
        ]);

        $request = BloodRequest::create([
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
            'blood_type' => 'O+',
            'units_required' => 1,
            'quantity' => 1,
            'requested_units' => 1,
            'urgency_level' => 'high',
            'city' => 'Manila',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'status' => 'matching',
            'matched_donors' => ['Reserved Elsewhere Donor'],
        ]);

        $otherRequest = BloodRequest::create([
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
            'blood_type' => 'O+',
            'units_required' => 1,
            'quantity' => 1,
            'requested_units' => 1,
            'urgency_level' => 'high',
            'city' => 'Manila',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'status' => 'matching',
        ]);

        RequestMatch::create([
            'blood_request_id' => $request->id,
            'request_id' => $request->id,
            'donor_id' => $donor->id,
            'score' => 91.5,
            'response_status' => 'accepted',
            'rank' => 1,
        ]);

        RequestMatch::create([
            'blood_request_id' => $otherRequest->id,
            'request_id' => $otherRequest->id,
            'donor_id' => $donor->id,
            'score' => 93.5,
            'response_status' => 'accepted',
            'rank' => 1,
        ]);

        $response = $this->actingAs($user)
            ->get(route('hospital.requests.matched-donors', $request));

        $response->assertOk();
        $response->assertSee('Reserved Elsewhere Donor');
        $response->assertSee('Coordination: Reserved here', false);
        $response->assertSee('Traffic Condition');
        $response->assertSee('Estimated Travel');
        $response->assertSee('08064444444');
        $response->assertSee($donorUser->email);
        $response->assertSee('Confirm Accepted Donor');
        $response->assertSee('Cancel Request');
    }
}

