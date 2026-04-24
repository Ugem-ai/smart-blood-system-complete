<?php

namespace Tests\Feature;

use App\Jobs\ProcessBloodRequestMatchingJob;
use App\Models\BloodRequest;
use App\Models\Donor;
use App\Models\Hospital;
use App\Models\RequestMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MultiHospitalCoordinationTest extends TestCase
{
    use RefreshDatabase;

    public function test_donor_cannot_be_double_allocated_across_two_hospitals(): void
    {
        $donorUser = User::factory()->create(['role' => 'donor']);
        $donor = Donor::create([
            'user_id' => $donorUser->id,
            'name' => 'Shared Donor',
            'blood_type' => 'O+',
            'city' => 'Manila',
            'contact_number' => '09170000444',
            'email' => $donorUser->email,
            'password' => 'Password123!',
            'availability' => true,
            'privacy_consent_at' => now(),
            'reliability_score' => 80,
            'latitude' => 14.5995,
            'longitude' => 120.9842,
        ]);

        [$hospitalUserA, $hospitalA] = $this->makeHospital('hospital-a@example.com', 'Hospital A');
        [$hospitalUserB, $hospitalB] = $this->makeHospital('hospital-b@example.com', 'Hospital B');

        $requestA = $this->makeRequest($hospitalA, 'O+');
        $requestB = $this->makeRequest($hospitalB, 'O+');

        app(ProcessBloodRequestMatchingJob::class, [
            'bloodRequestId' => $requestA->id,
            'actorUserId' => $hospitalUserA->id,
            'distanceLimitKm' => 50,
        ])->handle(
            app(\App\Services\DonorFilterService::class),
            app(\App\Algorithms\PASTMatch::class),
            app(\App\Services\MonitoringMetricsService::class)
        );

        app(ProcessBloodRequestMatchingJob::class, [
            'bloodRequestId' => $requestB->id,
            'actorUserId' => $hospitalUserB->id,
            'distanceLimitKm' => 50,
        ])->handle(
            app(\App\Services\DonorFilterService::class),
            app(\App\Algorithms\PASTMatch::class),
            app(\App\Services\MonitoringMetricsService::class)
        );

        $this->assertDatabaseHas('matches', [
            'blood_request_id' => $requestA->id,
            'donor_id' => $donor->id,
        ]);
        $this->assertDatabaseHas('matches', [
            'blood_request_id' => $requestB->id,
            'donor_id' => $donor->id,
        ]);

        Sanctum::actingAs($donorUser);

        $acceptA = $this->postJson('/api/donor/accept', [
            'blood_request_id' => $requestA->id,
        ]);

        $acceptA->assertOk();
        $acceptA->assertJsonPath('data.coordination.coordination_status', 'reserved_here');

        $acceptB = $this->postJson('/api/donor/accept', [
            'blood_request_id' => $requestB->id,
        ]);

        $acceptB->assertStatus(409);
        $acceptB->assertJsonPath('data.allocated_request_id', $requestA->id);

        $matchB = RequestMatch::query()
            ->where('blood_request_id', $requestB->id)
            ->where('donor_id', $donor->id)
            ->firstOrFail();

        $this->assertSame('expired', $matchB->response_status);
    }

    public function test_reserved_donor_is_excluded_from_future_matching(): void
    {
        $donorUser = User::factory()->create(['role' => 'donor']);
        $donor = Donor::create([
            'user_id' => $donorUser->id,
            'name' => 'Reserved Donor',
            'blood_type' => 'O+',
            'city' => 'Manila',
            'contact_number' => '09170000445',
            'email' => $donorUser->email,
            'password' => 'Password123!',
            'availability' => true,
            'privacy_consent_at' => now(),
            'reliability_score' => 80,
            'latitude' => 14.5995,
            'longitude' => 120.9842,
        ]);

        [$hospitalUserA, $hospitalA] = $this->makeHospital('hospital-c@example.com', 'Hospital C');
        [$hospitalUserB, $hospitalB] = $this->makeHospital('hospital-d@example.com', 'Hospital D');

        $requestA = $this->makeRequest($hospitalA, 'O+');

        app(ProcessBloodRequestMatchingJob::class, [
            'bloodRequestId' => $requestA->id,
            'actorUserId' => $hospitalUserA->id,
            'distanceLimitKm' => 50,
        ])->handle(
            app(\App\Services\DonorFilterService::class),
            app(\App\Algorithms\PASTMatch::class),
            app(\App\Services\MonitoringMetricsService::class)
        );

        Sanctum::actingAs($donorUser);
        $this->postJson('/api/donor/accept', [
            'blood_request_id' => $requestA->id,
        ])->assertOk();

        $requestB = $this->makeRequest($hospitalB, 'O+');

        app(ProcessBloodRequestMatchingJob::class, [
            'bloodRequestId' => $requestB->id,
            'actorUserId' => $hospitalUserB->id,
            'distanceLimitKm' => 50,
        ])->handle(
            app(\App\Services\DonorFilterService::class),
            app(\App\Algorithms\PASTMatch::class),
            app(\App\Services\MonitoringMetricsService::class)
        );

        $this->assertDatabaseMissing('matches', [
            'blood_request_id' => $requestB->id,
            'donor_id' => $donor->id,
        ]);
    }

    public function test_cancelled_request_releases_reserved_donor_for_future_matching(): void
    {
        $donorUser = User::factory()->create(['role' => 'donor']);
        $donor = Donor::create([
            'user_id' => $donorUser->id,
            'name' => 'Releasable Donor',
            'blood_type' => 'O+',
            'city' => 'Manila',
            'contact_number' => '09170000446',
            'email' => $donorUser->email,
            'password' => 'Password123!',
            'availability' => true,
            'privacy_consent_at' => now(),
            'reliability_score' => 82,
            'latitude' => 14.5995,
            'longitude' => 120.9842,
        ]);

        [$hospitalUserA, $hospitalA] = $this->makeHospital('hospital-e@example.com', 'Hospital E');
        [$hospitalUserB, $hospitalB] = $this->makeHospital('hospital-f@example.com', 'Hospital F');

        $requestA = $this->makeRequest($hospitalA, 'O+');

        app(ProcessBloodRequestMatchingJob::class, [
            'bloodRequestId' => $requestA->id,
            'actorUserId' => $hospitalUserA->id,
            'distanceLimitKm' => 50,
        ])->handle(
            app(\App\Services\DonorFilterService::class),
            app(\App\Algorithms\PASTMatch::class),
            app(\App\Services\MonitoringMetricsService::class)
        );

        Sanctum::actingAs($donorUser);
        $this->postJson('/api/donor/accept', [
            'blood_request_id' => $requestA->id,
        ])->assertOk();

        $this->actingAs($hospitalUserA)
            ->patch(route('hospital.requests.update-status', $requestA), [
                'status' => 'cancelled',
            ])
            ->assertRedirect();

        $requestA->refresh();
        $this->assertSame('cancelled', $requestA->status);

        $requestB = $this->makeRequest($hospitalB, 'O+');

        app(ProcessBloodRequestMatchingJob::class, [
            'bloodRequestId' => $requestB->id,
            'actorUserId' => $hospitalUserB->id,
            'distanceLimitKm' => 50,
        ])->handle(
            app(\App\Services\DonorFilterService::class),
            app(\App\Algorithms\PASTMatch::class),
            app(\App\Services\MonitoringMetricsService::class)
        );

        $this->assertDatabaseHas('matches', [
            'blood_request_id' => $requestB->id,
            'donor_id' => $donor->id,
        ]);

        Sanctum::actingAs($donorUser);
        $this->postJson('/api/donor/accept', [
            'blood_request_id' => $requestB->id,
        ])->assertOk()->assertJsonPath('data.coordination.coordination_status', 'reserved_here');
    }

    private function makeHospital(string $email, string $name): array
    {
        $user = User::factory()->create(['role' => 'hospital', 'email' => $email]);

        $hospital = Hospital::create([
            'user_id' => $user->id,
            'hospital_name' => $name,
            'address' => $name.' Address',
            'location' => 'Manila',
            'contact_person' => 'Dr '.$name,
            'contact_number' => '09179990009',
            'email' => $email,
            'password' => 'Password123!',
            'status' => 'approved',
        ]);

        return [$user, $hospital];
    }

    private function makeRequest(Hospital $hospital, string $bloodType): BloodRequest
    {
        return BloodRequest::create([
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
            'blood_type' => $bloodType,
            'units_required' => 1,
            'quantity' => 1,
            'requested_units' => 1,
            'urgency_level' => 'high',
            'city' => 'Manila',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'status' => 'pending',
        ]);
    }
}

