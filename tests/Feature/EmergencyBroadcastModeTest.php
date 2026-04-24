<?php

namespace Tests\Feature;

use App\Algorithms\PASTMatch;
use App\Jobs\SendEmergencyNotificationsJob;
use App\Jobs\ProcessBloodRequestMatchingJob;
use App\Models\BloodRequest;
use App\Models\Donor;
use App\Models\DonorAlertLog;
use App\Models\Hospital;
use App\Models\User;
use App\Services\DonorCooldownService;
use App\Services\EmergencyBroadcastModeService;
use App\Services\EmergencyEscalationService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmergencyBroadcastModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(EmergencyBroadcastModeService::class)->deactivate();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        app(EmergencyBroadcastModeService::class)->deactivate();
        parent::tearDown();
    }

    public function test_admin_can_activate_and_deactivate_emergency_broadcast_mode(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $activate = $this->patchJson('/api/admin/emergency-mode', [
            'enabled' => true,
            'trigger' => 'mass casualty incidents',
        ]);

        $activate->assertOk();
        $activate->assertJsonPath('data.enabled', true);
        $activate->assertJsonPath('data.trigger', 'mass casualty incidents');

        $status = $this->getJson('/api/admin/emergency-mode');
        $status->assertOk();
        $status->assertJsonPath('data.enabled', true);
        $status->assertJsonPath('data.trigger', 'mass casualty incidents');
        $status->assertJsonPath('data.emergency_mode.enabled', true);
        $status->assertJsonPath('data.emergency_mode.trigger', 'mass casualty incidents');
        $status->assertJsonPath('data.disaster_response_mode.active', false);

        $deactivate = $this->patchJson('/api/admin/emergency-mode', [
            'enabled' => false,
        ]);

        $deactivate->assertOk();
        $deactivate->assertJsonPath('data.enabled', false);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'emergency-broadcast-mode.activated',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'emergency-broadcast-mode.deactivated',
        ]);
    }

    public function test_only_admin_can_activate_emergency_mode(): void
    {
        $donorUser = User::factory()->create(['role' => 'donor']);
        Sanctum::actingAs($donorUser);

        $response = $this->patchJson('/api/admin/emergency-mode', [
            'enabled' => true,
            'trigger' => 'earthquake',
        ]);

        $response->assertForbidden();
    }

    public function test_emergency_state_survives_cache_reset_and_is_exposed_in_health_endpoint(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/emergency-mode', [
            'enabled' => true,
            'trigger' => 'earthquake',
            'expires_in_hours' => 6,
        ])->assertOk();

        $this->assertDatabaseHas('emergency_states', [
            'id' => 1,
            'is_active' => 1,
            'triggered_by' => $admin->id,
        ]);

        Cache::flush();

        $state = app(EmergencyBroadcastModeService::class)->state();

        $this->assertTrue($state['enabled']);
        $this->assertSame('earthquake', $state['trigger']);
        $this->assertNotNull($state['expires_at']);

        $this->getJson('/api/v1/monitor/health')
            ->assertJsonPath('emergency_mode.enabled', true)
            ->assertJsonPath('emergency_mode.trigger', 'earthquake');
    }

    public function test_emergency_mode_auto_expires_via_scheduler_command(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-30 08:00:00'));

        $admin = User::factory()->create(['role' => 'admin']);

        app(EmergencyBroadcastModeService::class)->activate('earthquake', $admin->id, 1);

        Carbon::setTestNow(Carbon::parse('2026-03-30 10:05:00'));

        $this->artisan('system:expire-emergency-mode')
            ->assertExitCode(0);

        $this->assertFalse(app(EmergencyBroadcastModeService::class)->isActive());
        $this->assertDatabaseHas('emergency_states', [
            'id' => 1,
            'is_active' => 0,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'emergency-broadcast-mode.deactivated',
        ]);
    }

    public function test_emergency_mode_changes_escalation_timing_and_matching_priority(): void
    {
        config([
            'services.notifications.emergency_escalation_delay_minutes' => 2,
            'services.notifications.emergency_low_urgency_max_delay_minutes' => 30,
            'services.notifications.emergency_priority_boost_factor' => 0.25,
        ]);

        $candidate = [
            'donor' => $this->makeDonor('A+', true, 120),
            'distance_km' => 18.0,
            'estimated_travel_minutes' => 20.0,
            'traffic_condition' => 'moderate',
            'traffic_multiplier' => 1.1,
            'transport_accessibility_score' => 80.0,
            'fastest_arrival_score' => 92.0,
        ];

        $algorithm = app(PASTMatch::class);
        $inactiveScore = $algorithm->rankDonors(new Collection([$candidate]))->first()['score'];

        $admin = User::factory()->create(['role' => 'admin']);
        app(EmergencyBroadcastModeService::class)->activate('earthquake', $admin->id);

        $activeScore = $algorithm->rankDonors(new Collection([$candidate]))->first()['score'];

        $this->assertGreaterThan($inactiveScore, $activeScore);
        $this->assertSame(2, app(EmergencyBroadcastModeService::class)->nextEscalationDelayMinutes());
        $this->assertSame(30, app(EmergencyBroadcastModeService::class)->maxNotificationDelayMinutesForUrgency('low'));
    }

    public function test_disaster_response_mode_forces_priority_requests_and_expands_radius(): void
    {
        Queue::fake();

        config([
            'services.notifications.disaster_expanded_radius_km' => 250,
            'services.notifications.disaster_force_priority_requests' => true,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/emergency-mode', [
            'enabled' => true,
            'trigger' => 'earthquake',
        ])->assertOk();

        $hospitalUser = User::factory()->create(['role' => 'hospital']);
        $hospital = Hospital::create([
            'user_id' => $hospitalUser->id,
            'hospital_name' => 'Disaster Hospital',
            'address' => 'Disaster Street',
            'location' => 'Manila',
            'contact_person' => 'Dr Disaster',
            'contact_number' => '09179990020',
            'email' => $hospitalUser->email,
            'password' => 'Password123!',
            'status' => 'approved',
        ]);

        Sanctum::actingAs($hospitalUser);

        $response = $this->postJson('/api/hospital/request', [
            'blood_type' => 'A+',
            'units_required' => 1,
            'urgency_level' => 'low',
            'city' => 'Manila',
            'distance_limit_km' => 25,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.urgency_level', 'high');
        $response->assertJsonPath('operational_mode.disaster_response_active', true);
        $response->assertJsonPath('operational_mode.priority_request_applied', true);
        $response->assertJsonPath('operational_mode.expanded_radius_km', 250);
        $response->assertJsonPath('operational_mode.mass_notification', true);

        Queue::assertPushed(ProcessBloodRequestMatchingJob::class, function (ProcessBloodRequestMatchingJob $job) use ($hospital) {
            $request = BloodRequest::query()->find($job->bloodRequestId);

            return $request !== null
                && (int) $request->hospital_id === (int) $hospital->id
                && $job->distanceLimitKm === 250;
        });
    }

    public function test_operational_mode_does_not_force_disaster_rules_when_not_active(): void
    {
        Queue::fake();

        config([
            'services.notifications.disaster_expanded_radius_km' => 250,
            'services.notifications.disaster_force_priority_requests' => true,
        ]);

        $hospitalUser = User::factory()->create(['role' => 'hospital']);
        $hospital = Hospital::create([
            'user_id' => $hospitalUser->id,
            'hospital_name' => 'Normal Operations Hospital',
            'address' => 'Normal Street',
            'location' => 'Manila',
            'contact_person' => 'Dr Normal',
            'contact_number' => '09179990022',
            'email' => $hospitalUser->email,
            'password' => 'Password123!',
            'status' => 'approved',
        ]);

        Sanctum::actingAs($hospitalUser);

        $response = $this->postJson('/api/hospital/request', [
            'blood_type' => 'A+',
            'units_required' => 1,
            'urgency_level' => 'low',
            'city' => 'Manila',
            'distance_limit_km' => 25,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.urgency_level', 'low');
        $response->assertJsonPath('operational_mode.disaster_response_active', false);
        $response->assertJsonPath('operational_mode.priority_request_applied', false);
        $response->assertJsonPath('operational_mode.expanded_radius_km', 25);
        $response->assertJsonPath('operational_mode.mass_notification', false);

        Queue::assertPushed(ProcessBloodRequestMatchingJob::class, function (ProcessBloodRequestMatchingJob $job) use ($hospital) {
            $request = BloodRequest::query()->find($job->bloodRequestId);

            return $request !== null
                && (int) $request->hospital_id === (int) $hospital->id
                && $job->distanceLimitKm === 25;
        });
    }

    public function test_disaster_response_mode_uses_mass_notification_behavior(): void
    {
        Http::fake([
            'https://fcm.googleapis.com/*' => Http::response(['success' => true], 200),
            'https://api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201),
            '*' => Http::response(['ok' => true], 200),
        ]);

        config([
            'services.notifications.max_alerts_per_day' => 100,
            'services.notifications.cooldown_hours' => 1,
            'services.notifications.pacing_us' => 0,
            'services.fcm.server_key' => 'test-fcm-key',
            'services.fcm.endpoint' => 'https://fcm.googleapis.com/fcm/send',
            'services.twilio.sid' => 'test-sid',
            'services.twilio.token' => 'test-token',
            'services.twilio.from' => '+15555550123',
        ]);

        $hospitalUser = User::factory()->create(['role' => 'hospital']);
        $hospital = Hospital::create([
            'user_id' => $hospitalUser->id,
            'hospital_name' => 'Disaster Broadcast Hospital',
            'address' => 'Broadcast Street',
            'location' => 'Manila',
            'contact_person' => 'Dr Disaster Broadcast',
            'contact_number' => '09179990021',
            'email' => $hospitalUser->email,
            'password' => 'Password123!',
            'status' => 'approved',
        ]);

        $request = BloodRequest::create([
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
            'blood_type' => 'O+',
            'units_required' => 2,
            'quantity' => 2,
            'requested_units' => 2,
            'urgency_level' => 'high',
            'city' => 'Manila',
            'status' => 'matching',
        ]);

        $eligibleA = $this->makeDonor('O+', true, 90);
        $eligibleB = $this->makeDonor('O-', true, 120);

        app(EmergencyBroadcastModeService::class)->activate('large-scale emergency', null);

        $job = new SendEmergencyNotificationsJob(
            bloodRequestId: $request->id,
            escalationLevel: EmergencyEscalationService::LEVEL_CLOSEST
        );

        $job->handle(
            app(NotificationService::class),
            app(EmergencyEscalationService::class),
            app(DonorCooldownService::class),
            app(EmergencyBroadcastModeService::class),
            app(\App\Services\DonorFilterService::class),
            app(\App\Services\DonorNotificationTimingService::class),
        );

        $this->assertDatabaseHas('donor_alert_logs', [
            'blood_request_id' => $request->id,
            'donor_id' => $eligibleA->id,
            'escalation_level' => EmergencyBroadcastModeService::BROADCAST_ESCALATION_LEVEL,
        ]);
        $this->assertDatabaseHas('donor_alert_logs', [
            'blood_request_id' => $request->id,
            'donor_id' => $eligibleB->id,
            'escalation_level' => EmergencyBroadcastModeService::BROADCAST_ESCALATION_LEVEL,
        ]);
    }

    public function test_emergency_mode_broadcast_notifies_all_compatible_eligible_donors(): void
    {
        Http::fake([
            'https://fcm.googleapis.com/*' => Http::response(['success' => true], 200),
            'https://api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201),
            '*' => Http::response(['ok' => true], 200),
        ]);

        config([
            'services.notifications.max_alerts_per_day' => 100,
            'services.notifications.cooldown_hours' => 1,
            'services.notifications.pacing_us' => 0,
            'services.fcm.server_key' => 'test-fcm-key',
            'services.fcm.endpoint' => 'https://fcm.googleapis.com/fcm/send',
            'services.twilio.sid' => 'test-sid',
            'services.twilio.token' => 'test-token',
            'services.twilio.from' => '+15555550123',
        ]);

        $hospitalUser = User::factory()->create(['role' => 'hospital']);
        $hospital = Hospital::create([
            'user_id' => $hospitalUser->id,
            'hospital_name' => 'Broadcast Hospital',
            'address' => 'Broadcast Street',
            'location' => 'Manila',
            'contact_person' => 'Dr Broadcast',
            'contact_number' => '09179990010',
            'email' => $hospitalUser->email,
            'password' => 'Password123!',
            'status' => 'approved',
        ]);

        $request = BloodRequest::create([
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
            'blood_type' => 'O+',
            'units_required' => 2,
            'quantity' => 2,
            'requested_units' => 2,
            'urgency_level' => 'high',
            'city' => 'Manila',
            'status' => 'matching',
        ]);

        $eligibleA = $this->makeDonor('O+', true, 90);
        $eligibleB = $this->makeDonor('O-', true, 120);
        $eligibleC = $this->makeDonor('O+', true, null);

        $this->makeDonor('A+', true, 120);   // incompatible
        $this->makeDonor('O+', false, 120);  // unavailable
        $this->makeDonor('O+', true, 20);    // donation interval not eligible

        app(EmergencyBroadcastModeService::class)->activate('disaster response', null);

        $job = new SendEmergencyNotificationsJob(
            bloodRequestId: $request->id,
            escalationLevel: EmergencyEscalationService::LEVEL_CLOSEST
        );

        $job->handle(
            app(NotificationService::class),
            app(EmergencyEscalationService::class),
            app(DonorCooldownService::class),
            app(EmergencyBroadcastModeService::class),
            app(\App\Services\DonorFilterService::class),
            app(\App\Services\DonorNotificationTimingService::class),
        );

        $this->assertDatabaseHas('donor_alert_logs', [
            'blood_request_id' => $request->id,
            'donor_id' => $eligibleA->id,
            'escalation_level' => EmergencyBroadcastModeService::BROADCAST_ESCALATION_LEVEL,
        ]);
        $this->assertDatabaseHas('donor_alert_logs', [
            'blood_request_id' => $request->id,
            'donor_id' => $eligibleB->id,
            'escalation_level' => EmergencyBroadcastModeService::BROADCAST_ESCALATION_LEVEL,
        ]);
        $this->assertDatabaseHas('donor_alert_logs', [
            'blood_request_id' => $request->id,
            'donor_id' => $eligibleC->id,
            'escalation_level' => EmergencyBroadcastModeService::BROADCAST_ESCALATION_LEVEL,
        ]);

        $this->assertSame(
            3,
            DonorAlertLog::query()
                ->where('blood_request_id', $request->id)
                ->where('escalation_level', EmergencyBroadcastModeService::BROADCAST_ESCALATION_LEVEL)
                ->count()
        );

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'emergency-broadcast-mode.broadcast-executed',
        ]);
    }

    private function makeDonor(string $bloodType, bool $available, ?int $daysSinceDonation): Donor
    {
        $user = User::factory()->create(['role' => 'donor']);

        return Donor::create([
            'user_id' => $user->id,
            'name' => 'Broadcast Donor '.$user->id,
            'blood_type' => $bloodType,
            'city' => 'Manila',
            'contact_number' => '09170000'.str_pad((string) $user->id, 3, '0', STR_PAD_LEFT),
            'phone' => '09170000'.str_pad((string) $user->id, 3, '0', STR_PAD_LEFT),
            'email' => $user->email,
            'password' => 'Password123!',
            'availability' => $available,
            'last_donation_date' => $daysSinceDonation !== null ? now()->subDays($daysSinceDonation)->toDateString() : null,
            'privacy_consent_at' => now(),
            'reliability_score' => 70,
        ]);
    }
}

