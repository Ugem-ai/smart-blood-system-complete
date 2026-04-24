<?php

namespace Tests\Feature;

use App\Jobs\SendEmergencyNotificationsJob;
use App\Jobs\SendTimedDonorAlertJob;
use App\Models\BloodRequest;
use App\Models\Donor;
use App\Models\DonorRequestResponse;
use App\Models\Hospital;
use App\Models\User;
use App\Services\DonorCooldownService;
use App\Services\DonorFilterService;
use App\Services\DonorNotificationTimingService;
use App\Services\EmergencyBroadcastModeService;
use App\Services\EmergencyEscalationService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Phase30SmartNotificationTimingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_notification_job_schedules_alert_for_donor_best_response_hour(): void
    {
        Queue::fake();

        Carbon::setTestNow(Carbon::parse('2026-03-21 08:10:00'));

        $hospital = $this->makeHospital();
        $request = $this->makeBloodRequest($hospital, [
            'urgency_level' => 'low',
            'status' => 'matching',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
        ]);

        $targetDonor = $this->makeDonor([
            'blood_type' => 'A+',
            'latitude' => 14.6000,
            'longitude' => 120.9842,
        ]);

        // Historical behavior: stronger acceptance at 09:00 than at current 08:00 hour.
        for ($i = 0; $i < 4; $i++) {
            $historicalRequest = $this->makeBloodRequest($hospital, [
                'status' => 'completed',
                'created_at' => Carbon::parse('2026-03-20 08:00:00')->subDays($i),
                'updated_at' => Carbon::parse('2026-03-20 08:00:00')->subDays($i),
            ]);

            DonorRequestResponse::create([
                'donor_id' => $targetDonor->id,
                'blood_request_id' => $historicalRequest->id,
                'response' => 'accepted',
                'responded_at' => Carbon::parse('2026-03-20 09:15:00')->subDays($i),
            ]);
        }

        for ($i = 0; $i < 4; $i++) {
            $historicalRequest = $this->makeBloodRequest($hospital, [
                'status' => 'completed',
                'created_at' => Carbon::parse('2026-03-20 07:00:00')->subDays($i),
                'updated_at' => Carbon::parse('2026-03-20 07:00:00')->subDays($i),
            ]);

            DonorRequestResponse::create([
                'donor_id' => $targetDonor->id,
                'blood_request_id' => $historicalRequest->id,
                'response' => 'declined',
                'responded_at' => Carbon::parse('2026-03-20 08:15:00')->subDays($i),
            ]);
        }

        $job = new SendEmergencyNotificationsJob(
            bloodRequestId: $request->id,
            escalationLevel: EmergencyEscalationService::LEVEL_CLOSEST
        );

        $job->handle(
            app(NotificationService::class),
            app(EmergencyEscalationService::class),
            app(DonorCooldownService::class),
            app(EmergencyBroadcastModeService::class),
            app(DonorFilterService::class),
            app(DonorNotificationTimingService::class)
        );

        Queue::assertPushed(SendTimedDonorAlertJob::class, function (SendTimedDonorAlertJob $queued) use ($request, $targetDonor) {
            return $queued->bloodRequestId === $request->id
                && $queued->donorId === $targetDonor->id
                && $queued->escalationLevel === EmergencyEscalationService::LEVEL_CLOSEST
                && $queued->delay !== null;
        });

        $this->assertDatabaseCount('donor_alert_logs', 0);

    }

    public function test_admin_dashboard_includes_smart_notification_timing_insights(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $hospital = $this->makeHospital();
        $donorA = $this->makeDonor([
            'name' => 'Speed Donor A',
            'blood_type' => 'A+',
        ]);
        $donorB = $this->makeDonor([
            'name' => 'Speed Donor B',
            'blood_type' => 'O+',
        ]);

        for ($i = 0; $i < 3; $i++) {
            $requestA = $this->makeBloodRequest($hospital, ['status' => 'completed']);
            DonorRequestResponse::create([
                'donor_id' => $donorA->id,
                'blood_request_id' => $requestA->id,
                'response' => 'accepted',
                'responded_at' => Carbon::parse('2026-03-21 19:10:00')->subDays($i),
            ]);
        }

        for ($i = 0; $i < 2; $i++) {
            $requestB = $this->makeBloodRequest($hospital, ['status' => 'completed']);
            DonorRequestResponse::create([
                'donor_id' => $donorB->id,
                'blood_request_id' => $requestB->id,
                'response' => 'declined',
                'responded_at' => Carbon::parse('2026-03-21 10:30:00')->subDays($i),
            ]);
        }

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertOk();
        $response->assertJsonStructure([
            'smart_notification_timing' => [
                'top_response_hours',
                'donor_profiles',
            ],
        ]);

        $topHours = collect($response->json('smart_notification_timing.top_response_hours'));
        $this->assertTrue($topHours->contains(fn (array $hourRow) => ($hourRow['hour'] ?? null) === 19));

        $profiles = collect($response->json('smart_notification_timing.donor_profiles'));
        $this->assertTrue($profiles->contains(fn (array $profile) => ($profile['donor_id'] ?? null) === $donorA->id));
    }

    private function makeDonor(array $overrides = []): Donor
    {
        $user = User::factory()->create(['role' => 'donor']);

        return Donor::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Timing Donor '.$user->id,
            'blood_type' => 'A+',
            'city' => 'Manila',
            'contact_number' => '09170000001',
            'email' => $user->email,
            'password' => 'Password123!',
            'availability' => true,
            'privacy_consent_at' => now(),
            'reliability_score' => 70,
            'latitude' => 14.5995,
            'longitude' => 120.9842,
        ], $overrides));
    }

    private function makeHospital(): Hospital
    {
        $user = User::factory()->create(['role' => 'hospital']);

        return Hospital::create([
            'user_id' => $user->id,
            'hospital_name' => 'Timing Hospital',
            'address' => '123 Test St',
            'location' => 'Manila',
            'contact_person' => 'Dr Timing',
            'contact_number' => '09179999999',
            'email' => $user->email,
            'password' => 'Password123!',
            'status' => 'approved',
        ]);
    }

    private function makeBloodRequest(Hospital $hospital, array $overrides = []): BloodRequest
    {
        return BloodRequest::create(array_merge([
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
            'blood_type' => 'A+',
            'units_required' => 1,
            'quantity' => 1,
            'requested_units' => 1,
            'urgency_level' => 'medium',
            'city' => 'Manila',
            'status' => 'pending',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
        ], $overrides));
    }
}
