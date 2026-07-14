<?php

namespace Tests\Feature;

use App\Algorithms\PASTMatch;
use App\Models\BloodRequest;
use App\Models\Donor;
use App\Models\DonorRequestAcceptance;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DonorRequestAcceptanceFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_donor_can_accept_long_distance_request(): void
    {
        [$donorUser, $donor, $bloodRequest] = $this->seedEmergencyRequestContext();

        DonorRequestAcceptance::query()->create([
            'donor_id' => $donor->id,
            'blood_request_id' => $bloodRequest->id,
            'distance_km_at_acceptance' => 17.4,
            'status' => 'pending',
            'accepted_at' => null,
        ]);

        $response = $this->actingAs($donorUser)
            ->postJson('/api/blood-requests/'.$bloodRequest->id.'/acceptances', [
                'status' => 'accepted',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseHas('donor_request_acceptances', [
            'donor_id' => $donor->id,
            'blood_request_id' => $bloodRequest->id,
            'status' => 'accepted',
        ]);

        $this->assertNotNull(
            DonorRequestAcceptance::query()
                ->where('donor_id', $donor->id)
                ->where('blood_request_id', $bloodRequest->id)
                ->value('accepted_at')
        );
    }

    public function test_donor_can_decline_long_distance_request(): void
    {
        [$donorUser, $donor, $bloodRequest] = $this->seedEmergencyRequestContext();

        DonorRequestAcceptance::query()->create([
            'donor_id' => $donor->id,
            'blood_request_id' => $bloodRequest->id,
            'distance_km_at_acceptance' => 18.6,
            'status' => 'pending',
            'accepted_at' => null,
        ]);

        $response = $this->actingAs($donorUser)
            ->postJson('/api/blood-requests/'.$bloodRequest->id.'/acceptances', [
                'status' => 'declined',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'declined');

        $this->assertDatabaseHas('donor_request_acceptances', [
            'donor_id' => $donor->id,
            'blood_request_id' => $bloodRequest->id,
            'status' => 'declined',
        ]);

        $this->assertNull(
            DonorRequestAcceptance::query()
                ->where('donor_id', $donor->id)
                ->where('blood_request_id', $bloodRequest->id)
                ->value('accepted_at')
        );
    }

    public function test_expired_request_auto_declines_pending_when_donor_response_window_has_passed(): void
    {
        [$donorUser, $donor, $bloodRequest] = $this->seedEmergencyRequestContext(expired: true);

        DonorRequestAcceptance::query()->create([
            'donor_id' => $donor->id,
            'blood_request_id' => $bloodRequest->id,
            'distance_km_at_acceptance' => 32.5,
            'status' => 'pending',
            'accepted_at' => null,
        ]);

        $this->actingAs($donorUser)
            ->postJson('/api/blood-requests/'.$bloodRequest->id.'/acceptances', [
                'status' => 'accepted',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This emergency request has expired.');

        $this->assertDatabaseHas('donor_request_acceptances', [
            'donor_id' => $donor->id,
            'blood_request_id' => $bloodRequest->id,
            'status' => 'declined',
        ]);
    }

    public function test_admin_can_view_request_travel_acceptances(): void
    {
        [, $donor, $bloodRequest] = $this->seedEmergencyRequestContext();
        $adminUser = User::factory()->create(['role' => 'admin']);

        DonorRequestAcceptance::query()->create([
            'donor_id' => $donor->id,
            'blood_request_id' => $bloodRequest->id,
            'distance_km_at_acceptance' => 19.2,
            'status' => 'pending',
            'accepted_at' => null,
        ]);

        $this->actingAs($adminUser)
            ->getJson('/api/admin/requests/'.$bloodRequest->id.'/travel-acceptances')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonPath('data.items.0.donor_id', $donor->id)
            ->assertJsonPath('data.items.0.status', 'pending');
    }

    public function test_donor_cannot_respond_without_pending_invitation(): void
    {
        [$donorUser, $donor, $bloodRequest] = $this->seedEmergencyRequestContext();

        $this->actingAs($donorUser)
            ->postJson('/api/blood-requests/'.$bloodRequest->id.'/acceptances', [
                'status' => 'accepted',
            ])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No travel acceptance invitation found for this request.');

        $this->assertDatabaseMissing('donor_request_acceptances', [
            'donor_id' => $donor->id,
            'blood_request_id' => $bloodRequest->id,
            'status' => 'accepted',
        ]);
    }

    public function test_hospital_can_view_its_request_travel_acceptances(): void
    {
        [$hospitalUser, $donor, $bloodRequest] = $this->seedEmergencyRequestContextWithHospitalUser();

        DonorRequestAcceptance::query()->create([
            'donor_id' => $donor->id,
            'blood_request_id' => $bloodRequest->id,
            'distance_km_at_acceptance' => 21.8,
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $this->actingAs($hospitalUser)
            ->getJson('/api/hospital/requests/'.$bloodRequest->id.'/travel-acceptances')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonPath('data.items.0.status', 'accepted');
    }

    public function test_past_match_overrides_travel_willingness_for_accepted_request_acceptance(): void
    {
        [, $donor, $bloodRequest] = $this->seedEmergencyRequestContext();

        DonorRequestAcceptance::query()->create([
            'donor_id' => $donor->id,
            'blood_request_id' => $bloodRequest->id,
            'distance_km_at_acceptance' => 27.3,
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $ranked = app(PASTMatch::class)->rankDonors(collect([
            [
                'donor' => $donor,
                'distance_km' => 27.3,
                'estimated_travel_minutes' => 45,
                'traffic_condition' => 'moderate',
                'traffic_multiplier' => 1.2,
                'transport_accessibility_score' => 60,
                'fastest_arrival_score' => 55,
            ],
        ]), [
            'urgency_level' => $bloodRequest->urgency_level,
            'blood_request_id' => $bloodRequest->id,
        ]);

        $this->assertSame(100.0, $ranked->first()['factors']['travel_willingness']);
    }

    /**
     * @return array{0: User, 1: Donor, 2: BloodRequest}
     */
    private function seedEmergencyRequestContext(bool $expired = false): array
    {
        $hospitalUser = User::factory()->create(['role' => 'hospital']);
        $hospital = Hospital::query()->create([
            'user_id' => $hospitalUser->id,
            'hospital_name' => 'Rapid Care Medical',
            'location' => 'Cebu',
            'contact_person' => 'Dr Swift',
            'contact_number' => '09170000001',
            'email' => $hospitalUser->email,
            'password' => 'password',
            'status' => 'approved',
        ]);

        $bloodRequest = BloodRequest::query()->create([
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
            'blood_type' => 'A+',
            'quantity' => 1,
            'requested_units' => 1,
            'urgency_level' => 'high',
            'city' => 'Cebu',
            'is_emergency' => true,
            'latitude' => 10.3157,
            'longitude' => 123.8854,
            'expiry_time' => $expired ? now()->subMinutes(10) : now()->addMinutes(30),
            'status' => 'matching',
        ]);

        $donorUser = User::factory()->create(['role' => 'donor']);
        $donor = Donor::query()->create([
            'user_id' => $donorUser->id,
            'name' => $donorUser->name,
            'blood_type' => 'A+',
            'city' => 'Talisay',
            'contact_number' => '09170000002',
            'email' => $donorUser->email,
            'password' => 'password',
            'availability' => true,
            'privacy_consent_at' => now(),
            'latitude' => 10.2447,
            'longitude' => 123.8494,
            'normal_travel_radius' => 5,
        ]);

        return [$donorUser, $donor, $bloodRequest];
    }

    /**
     * @return array{0: User, 1: Donor, 2: BloodRequest}
     */
    private function seedEmergencyRequestContextWithHospitalUser(bool $expired = false): array
    {
        $hospitalUser = User::factory()->create(['role' => 'hospital']);
        $hospital = Hospital::query()->create([
            'user_id' => $hospitalUser->id,
            'hospital_name' => 'Rapid Care Medical',
            'location' => 'Cebu',
            'contact_person' => 'Dr Swift',
            'contact_number' => '09170000001',
            'email' => $hospitalUser->email,
            'password' => 'password',
            'status' => 'approved',
        ]);

        $bloodRequest = BloodRequest::query()->create([
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
            'blood_type' => 'A+',
            'quantity' => 1,
            'requested_units' => 1,
            'urgency_level' => 'high',
            'city' => 'Cebu',
            'is_emergency' => true,
            'latitude' => 10.3157,
            'longitude' => 123.8854,
            'expiry_time' => $expired ? now()->subMinutes(10) : now()->addMinutes(30),
            'status' => 'matching',
        ]);

        $donorUser = User::factory()->create(['role' => 'donor']);
        $donor = Donor::query()->create([
            'user_id' => $donorUser->id,
            'name' => $donorUser->name,
            'blood_type' => 'A+',
            'city' => 'Talisay',
            'contact_number' => '09170000002',
            'email' => $donorUser->email,
            'password' => 'password',
            'availability' => true,
            'privacy_consent_at' => now(),
            'latitude' => 10.2447,
            'longitude' => 123.8494,
            'normal_travel_radius' => 5,
        ]);

        return [$hospitalUser, $donor, $bloodRequest];
    }
}
