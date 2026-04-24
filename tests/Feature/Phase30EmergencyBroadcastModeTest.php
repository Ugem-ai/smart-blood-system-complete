<?php

namespace Tests\Feature;

use App\Jobs\SendEmergencyNotificationsJob;
use App\Models\BloodRequest;
use App\Models\Donor;
use App\Models\DonorAlertLog;
use App\Models\Hospital;
use App\Models\User;
use App\Services\DonorCooldownService;
use App\Services\EmergencyBroadcastModeService;
use App\Services\EmergencyEscalationService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Phase30EmergencyBroadcastModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(EmergencyBroadcastModeService::class)->deactivate();
    }

    protected function tearDown(): void
    {
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
