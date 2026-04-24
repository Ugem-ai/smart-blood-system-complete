<?php

namespace Tests\Feature;

use App\Models\BloodRequest;
use App\Models\Donor;
use App\Models\Hospital;
use App\Models\RequestMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanctum_token_expiration_is_configured(): void
    {
        $this->assertNotNull(config('sanctum.expiration'));
        $this->assertGreaterThan(0, (int) config('sanctum.expiration'));
    }

    public function test_login_endpoint_is_rate_limited(): void
    {
        $user = User::factory()->create([
            'email' => 'ratelimit@example.com',
            'password' => Hash::make('Password123!'),
            'role' => 'donor',
        ]);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/login', [
                'email' => $user->email,
                'password' => 'Password123!',
            ])->assertOk();
        }

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ])->assertStatus(429);
    }

    public function test_hospital_can_view_sensitive_matched_donor_data_only_for_owned_request(): void
    {
        $hospitalUser = User::factory()->create(['role' => 'hospital']);
        $hospital = Hospital::create([
            'user_id' => $hospitalUser->id,
            'hospital_name' => 'Secure Hospital',
            'address' => 'Secure Address',
            'location' => 'Secure Address',
            'contact_person' => 'Dr Secure',
            'contact_number' => '09170000111',
            'email' => $hospitalUser->email,
            'password' => 'Password123!',
            'status' => 'approved',
        ]);

        $otherHospitalUser = User::factory()->create(['role' => 'hospital']);
        Hospital::create([
            'user_id' => $otherHospitalUser->id,
            'hospital_name' => 'Other Hospital',
            'address' => 'Other Address',
            'location' => 'Other Address',
            'contact_person' => 'Dr Other',
            'contact_number' => '09170000222',
            'email' => $otherHospitalUser->email,
            'password' => 'Password123!',
            'status' => 'approved',
        ]);

        $donorUser = User::factory()->create(['role' => 'donor']);
        $donor = Donor::create([
            'user_id' => $donorUser->id,
            'name' => 'Sensitive Donor',
            'blood_type' => 'A+',
            'city' => 'Manila',
            'contact_number' => '09170000333',
            'phone' => '09170000333',
            'email' => $donorUser->email,
            'password' => 'Password123!',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'availability' => true,
            'reliability_score' => 80,
            'privacy_consent_at' => now(),
        ]);

        $bloodRequest = BloodRequest::create([
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
            'blood_type' => 'A+',
            'units_required' => 1,
            'quantity' => 1,
            'requested_units' => 1,
            'urgency_level' => 'high',
            'city' => 'Manila',
            'status' => 'matching',
        ]);

        RequestMatch::create([
            'blood_request_id' => $bloodRequest->id,
            'request_id' => $bloodRequest->id,
            'donor_id' => $donor->id,
            'score' => 95,
            'response_status' => 'pending',
            'rank' => 1,
        ]);

        Sanctum::actingAs($hospitalUser);
        $authorized = $this->getJson('/api/hospital/request/'.$bloodRequest->id.'/matched-donors');
        $authorized->assertOk();
        $authorized->assertJsonPath('data.donors.0.contact_number', '09170000333');
        $authorized->assertJsonPath('data.donors.0.email', $donorUser->email);
        $authorized->assertJsonPath('data.donors.0.latitude', '14.5995000');

        Sanctum::actingAs($otherHospitalUser);
        $this->getJson('/api/hospital/request/'.$bloodRequest->id.'/matched-donors')
            ->assertStatus(403);
    }

    public function test_donor_role_cannot_access_hospital_sensitive_matched_donor_endpoint(): void
    {
        $hospitalUser = User::factory()->create(['role' => 'hospital']);
        $hospital = Hospital::create([
            'user_id' => $hospitalUser->id,
            'hospital_name' => 'Restricted Hospital',
            'address' => 'Restricted Address',
            'location' => 'Restricted Address',
            'contact_person' => 'Dr Restricted',
            'contact_number' => '09170000999',
            'email' => $hospitalUser->email,
            'password' => 'Password123!',
            'status' => 'approved',
        ]);

        $bloodRequest = BloodRequest::create([
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
            'blood_type' => 'A+',
            'units_required' => 1,
            'quantity' => 1,
            'requested_units' => 1,
            'urgency_level' => 'high',
            'city' => 'Manila',
            'status' => 'matching',
        ]);

        $donorUser = User::factory()->create(['role' => 'donor']);

        Sanctum::actingAs($donorUser);

        $this->getJson('/api/hospital/request/'.$bloodRequest->id.'/matched-donors')
            ->assertStatus(403);
    }
}

