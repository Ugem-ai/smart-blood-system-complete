<?php

namespace Tests\Feature;

use App\Jobs\SendEmergencyNotificationsJob;
use App\Models\BloodRequest;
use App\Models\Donor;
use App\Models\DonorAlertLog;
use App\Models\Hospital;
use App\Models\User;
use App\Services\DonorCooldownService;
use App\Services\EmergencyEscalationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Phase30EscalationAndCooldownTest extends TestCase
{
    use RefreshDatabase;

    public function test_emergency_escalation_windows_return_expected_recipient_batches(): void
    {
        $hospital = $this->makeHospital();

        $request = BloodRequest::create([
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
            'blood_type' => 'A+',
            'units_required' => 1,
            'quantity' => 1,
            'requested_units' => 1,
            'urgency_level' => 'high',
            'city' => 'Manila',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'status' => 'matching',
        ]);

        // 20 compatible donors with increasing distance from request point.
        for ($i = 0; $i < 20; $i++) {
            $this->makeDonor([
                'blood_type' => 'A+',
                'latitude' => 14.5995 + ($i * 0.005),
                'longitude' => 120.9842,
            ]);
        }

        $service = app(EmergencyEscalationService::class);

        $level1 = $service->recipientsForLevel($request, EmergencyEscalationService::LEVEL_CLOSEST);
        $level2 = $service->recipientsForLevel($request, EmergencyEscalationService::LEVEL_WIDER_RADIUS);
        $level3 = $service->recipientsForLevel($request, EmergencyEscalationService::LEVEL_ALL_COMPATIBLE);

        $this->assertCount(5, $level1, '0-5 min should target closest donors.');
        $this->assertCount(10, $level2, '5-10 min should target wider radius batch.');
        $this->assertCount(5, $level3, '10-20 min should target all remaining compatible donors.');
    }

    public function test_donor_cooldown_and_daily_cap_are_enforced(): void
    {
        config([
            'services.notifications.max_alerts_per_day' => 3,
            'services.notifications.cooldown_hours' => 12,
        ]);

        $donor = $this->makeDonor();
        $hospital = $this->makeHospital();
        $request = $this->makeBloodRequest($hospital);

        $cooldown = app(DonorCooldownService::class);

        // First alert allowed.
        $this->assertTrue($cooldown->canNotifyDonor($donor));
        $cooldown->recordAlert($request, $donor, 1);

        // Immediate second alert blocked by 12-hour cooldown.
        $this->assertFalse($cooldown->canNotifyDonor($donor));

        // Seed 2 additional alerts today after cooldown windows to hit daily cap of 3.
        DonorAlertLog::query()->create([
            'blood_request_id' => $request->id,
            'donor_id' => $donor->id,
            'escalation_level' => 2,
            'channel' => 'multi',
            'sent_at' => now()->subHours(13),
        ]);
        DonorAlertLog::query()->create([
            'blood_request_id' => $request->id,
            'donor_id' => $donor->id,
            'escalation_level' => 3,
            'channel' => 'multi',
            'sent_at' => now()->subHours(14),
        ]);

        $this->assertFalse($cooldown->canNotifyDonor($donor), 'Daily max alerts should block additional notifications.');
    }

    public function test_escalation_job_records_alerts_and_schedules_next_level(): void
    {
        Queue::fake();

        config([
            'services.notifications.max_alerts_per_day' => 3,
            'services.notifications.cooldown_hours' => 12,
            'services.notifications.max_burst' => 20,
        ]);

        $hospital = $this->makeHospital();

        $request = $this->makeBloodRequest($hospital, [
            'status' => 'matching',
            'urgency_level' => 'high',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
        ]);

        $this->makeDonor([
            'blood_type' => 'A+',
            'latitude' => 14.6000,
            'longitude' => 120.9842,
            'phone' => null,
        ]);

        $job = new SendEmergencyNotificationsJob(
            bloodRequestId: $request->id,
            escalationLevel: EmergencyEscalationService::LEVEL_CLOSEST
        );

        $job->handle(
            app(\App\Services\NotificationService::class),
            app(EmergencyEscalationService::class),
            app(DonorCooldownService::class),
            app(\App\Services\EmergencyBroadcastModeService::class),
            app(\App\Services\DonorFilterService::class),
            app(\App\Services\DonorNotificationTimingService::class)
        );

        $this->assertDatabaseCount('donor_alert_logs', 1);

        Queue::assertPushed(SendEmergencyNotificationsJob::class, function (SendEmergencyNotificationsJob $next) use ($request) {
            return $next->bloodRequestId === $request->id
                && $next->escalationLevel === 2;
        });
    }

    private function makeDonor(array $overrides = []): Donor
    {
        $user = User::factory()->create(['role' => 'donor']);

        return Donor::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Escalation Donor '.$user->id,
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
            'hospital_name' => 'Escalation Hospital',
            'address' => '123 Test St',
            'location' => 'Manila',
            'contact_person' => 'Dr Escalation',
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
            'urgency_level' => 'high',
            'city' => 'Manila',
            'status' => 'pending',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
        ], $overrides));
    }
}
