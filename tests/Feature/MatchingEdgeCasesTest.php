<?php

namespace Tests\Feature;

use App\Jobs\ProcessBloodRequestMatchingJob;
use App\Models\BloodRequest;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MatchingEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_hospital_request_api_creates_request_and_dispatches_matching_job(): void
    {
        Queue::fake();

        $hospitalUser = User::factory()->create(['role' => 'hospital']);
        $hospital = Hospital::create([
            'user_id' => $hospitalUser->id,
            'hospital_name' => 'API Request Hospital',
            'address' => 'Request Street',
            'location' => 'Manila',
            'contact_person' => 'Dr Request',
            'contact_number' => '09179990031',
            'email' => $hospitalUser->email,
            'password' => 'Password123!',
            'status' => 'approved',
        ]);

        Sanctum::actingAs($hospitalUser);

        $response = $this->postJson('/api/hospital/request', [
            'blood_type' => 'O+',
            'units_required' => 2,
            'urgency_level' => 'high',
            'city' => 'Manila',
            'distance_limit_km' => 60,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.hospital_id', $hospital->id);
        $response->assertJsonPath('data.blood_type', 'O+');
        $response->assertJsonPath('operational_mode.expanded_radius_km', 60);

        Queue::assertPushed(ProcessBloodRequestMatchingJob::class, function (ProcessBloodRequestMatchingJob $job) use ($hospital) {
            $request = BloodRequest::query()->find($job->bloodRequestId);

            return $request !== null
                && (int) $request->hospital_id === (int) $hospital->id
                && $job->distanceLimitKm === 60;
        });
    }

    public function test_matching_job_leaves_request_pending_when_no_eligible_donors_exist(): void
    {
        $hospitalUser = User::factory()->create(['role' => 'hospital']);
        $hospital = Hospital::create([
            'user_id' => $hospitalUser->id,
            'hospital_name' => 'No Donor Hospital',
            'address' => 'No Match Street',
            'location' => 'Manila',
            'contact_person' => 'Dr No Match',
            'contact_number' => '09179990032',
            'email' => $hospitalUser->email,
            'password' => 'Password123!',
            'status' => 'approved',
        ]);

        $request = BloodRequest::create([
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
            'blood_type' => 'AB-',
            'units_required' => 1,
            'quantity' => 1,
            'requested_units' => 1,
            'urgency_level' => 'high',
            'city' => 'Manila',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'status' => 'pending',
        ]);

        $job = new ProcessBloodRequestMatchingJob(
            bloodRequestId: $request->id,
            actorUserId: $hospitalUser->id,
            distanceLimitKm: 50
        );

        $job->handle(
            app(\App\Services\DonorFilterService::class),
            app(\App\Algorithms\PASTMatch::class),
            app(\App\Services\MonitoringMetricsService::class)
        );

        $request->refresh();

        $this->assertSame('pending', $request->status);
        $this->assertSame([], $request->matched_donors ?? []);
        $this->assertDatabaseCount('matches', 0);
    }
}