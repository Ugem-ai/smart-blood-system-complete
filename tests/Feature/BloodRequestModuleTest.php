<?php

namespace Tests\Feature;

use App\Models\BloodRequest;
use App\Models\Donor;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BloodRequestModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_hospital_can_create_blood_request(): void
    {
        $user = User::factory()->create(['role' => 'hospital']);
        $hospital = Hospital::create([
            'user_id' => $user->id,
            'hospital_name' => 'City Health',
            'location' => 'Abuja',
            'contact_person' => 'Dr City',
            'contact_number' => '08010000000',
            'email' => $user->email,
            'password' => 'password',
            'status' => 'approved',
        ]);

        $donorUser = User::factory()->create(['role' => 'donor']);
        $donor = Donor::create([
            'user_id' => $donorUser->id,
            'name' => $donorUser->name,
            'blood_type' => 'B+',
            'city' => 'Abuja',
            'contact_number' => '08015555555',
            'email' => $donorUser->email,
            'password' => 'password',
            'last_donation_date' => now()->subDays(100)->toDateString(),
            'availability' => true,
            'privacy_consent_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('hospital.requests.submit'), [
            'blood_type' => 'B+',
            'city' => 'Abuja',
            'quantity' => 2,
            'urgency_level' => 'high',
            'required_on' => '2026-03-22',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('blood_requests', [
            'hospital_id' => $hospital->id,
            'blood_type' => 'B+',
            'quantity' => 2,
            'urgency_level' => 'high',
            'status' => 'matching',
        ]);

        $bloodRequest = BloodRequest::query()->where('hospital_id', $hospital->id)->firstOrFail();

        $this->assertDatabaseHas('matches', [
            'blood_request_id' => $bloodRequest->id,
            'donor_id' => $donor->id,
            'rank' => 1,
        ]);
    }

    public function test_hospital_can_update_request_status(): void
    {
        $user = User::factory()->create(['role' => 'hospital']);
        $hospital = Hospital::create([
            'user_id' => $user->id,
            'hospital_name' => 'Life Aid',
            'location' => 'Lagos',
            'contact_person' => 'Dr Aid',
            'contact_number' => '08020000000',
            'email' => $user->email,
            'password' => 'password',
            'status' => 'approved',
        ]);

        $requestRecord = BloodRequest::create([
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
            'blood_type' => 'O-',
            'quantity' => 1,
            'urgency_level' => 'medium',
            'city' => 'Lagos',
            'requested_units' => 1,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->patch(route('hospital.requests.update-status', $requestRecord), ['status' => 'matching'])
            ->assertRedirect();

        $this->assertDatabaseHas('blood_requests', [
            'id' => $requestRecord->id,
            'status' => 'matching',
        ]);
    }

    public function test_donor_can_accept_or_decline_request(): void
    {
        $hospitalUser = User::factory()->create(['role' => 'hospital']);
        $hospital = Hospital::create([
            'user_id' => $hospitalUser->id,
            'hospital_name' => 'Hope Clinic',
            'location' => 'Kano',
            'contact_person' => 'Dr Hope',
            'contact_number' => '08030000000',
            'email' => $hospitalUser->email,
            'password' => 'password',
            'status' => 'approved',
        ]);

        $bloodRequest = BloodRequest::create([
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
            'blood_type' => 'A+',
            'quantity' => 1,
            'urgency_level' => 'high',
            'city' => 'Kaduna',
            'requested_units' => 1,
            'status' => 'pending',
        ]);

        $donorUser = User::factory()->create(['role' => 'donor']);
        $donor = Donor::create([
            'user_id' => $donorUser->id,
            'name' => $donorUser->name,
            'blood_type' => 'A+',
            'city' => 'Kaduna',
            'contact_number' => '08040000000',
            'email' => $donorUser->email,
            'password' => 'password',
            'availability' => true,
            'privacy_consent_at' => now(),
        ]);

        $this->actingAs($donorUser)
            ->patch(route('donor.requests.respond', $bloodRequest), ['response' => 'accepted'])
            ->assertRedirect();

        $this->assertDatabaseHas('donor_request_responses', [
            'donor_id' => $donor->id,
            'blood_request_id' => $bloodRequest->id,
            'response' => 'accepted',
        ]);

        $this->assertDatabaseHas('blood_requests', [
            'id' => $bloodRequest->id,
            'status' => 'matching',
        ]);
    }
}

