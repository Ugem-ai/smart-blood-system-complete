<?php

namespace Tests\Feature;

use App\Models\BloodRequest;
use App\Models\DonationHistory;
use App\Models\Donor;
use App\Models\ActivityLog;
use App\Models\DonorAlertLog;
use App\Models\DonorRequestResponse;
use App\Models\Hospital;
use App\Models\NotificationDelivery;
use App\Models\RequestMatch;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\EmergencyBroadcastModeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminModuleTest extends TestCase
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

    public function test_admin_can_approve_hospital(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $hospitalUser = User::factory()->create(['role' => 'hospital']);

        $hospital = Hospital::create([
            'user_id' => $hospitalUser->id,
            'hospital_name' => 'Mercy Hospital',
            'location' => 'Lagos',
            'contact_person' => 'Dr Mercy',
            'contact_number' => '08090000000',
            'email' => $hospitalUser->email,
            'password' => 'password',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.hospitals.approve', $hospital))
            ->assertRedirect();

        $this->assertDatabaseHas('hospitals', [
            'id' => $hospital->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'hospital.approved',
        ]);
    }

    public function test_admin_dashboard_contains_monitoring_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $donorUser1 = User::factory()->create(['role' => 'donor']);
        $donorUser2 = User::factory()->create(['role' => 'donor']);

        $donor1 = Donor::create([
            'user_id' => $donorUser1->id,
            'name' => $donorUser1->name,
            'blood_type' => 'A+',
            'city' => 'Abuja',
            'contact_number' => '08081111111',
            'email' => $donorUser1->email,
            'password' => 'password',
            'availability' => true,
            'privacy_consent_at' => now(),
        ]);

        Donor::create([
            'user_id' => $donorUser2->id,
            'name' => $donorUser2->name,
            'blood_type' => 'O-',
            'city' => 'Kano',
            'contact_number' => '08082222222',
            'email' => $donorUser2->email,
            'password' => 'password',
            'availability' => false,
            'privacy_consent_at' => now(),
        ]);

        DonationHistory::create([
            'donor_id' => $donor1->id,
            'donated_at' => now()->subDay(),
            'location' => 'City Center',
            'units' => 1,
        ]);

        BloodRequest::create([
            'hospital_name' => 'General Hospital',
            'blood_type' => 'A+',
            'city' => 'Abuja',
            'requested_units' => 2,
            'status' => 'open',
        ]);

        BloodRequest::create([
            'hospital_name' => 'Care Clinic',
            'blood_type' => 'O-',
            'city' => 'Kano',
            'requested_units' => 1,
            'status' => 'fulfilled',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('stats', function (array $stats) {
            return $stats['donations'] === 1
                && $stats['requests'] === 2
                && $stats['active_donors'] === 1;
        });
        $response->assertViewHas('donors');
        $response->assertViewHas('bloodRequests');
        $response->assertViewHas('activityLogs');
        $response->assertViewHas('emergencyDashboard', function (array $emergencyDashboard) {
            return isset($emergencyDashboard['counts'])
                && array_key_exists('live_blood_requests', $emergencyDashboard['counts'])
                && array_key_exists('active_donor_alerts', $emergencyDashboard['counts'])
                && array_key_exists('accepted_requests', $emergencyDashboard['counts'])
                && array_key_exists('donations_completed', $emergencyDashboard['counts']);
        });
        $response->assertViewHas('emergencyMode', function (array $emergencyMode) {
            return $emergencyMode['enabled'] === false
                && $emergencyMode['trigger'] === null;
        });
        $response->assertViewHas('disasterResponseMode', function (array $disasterResponseMode) {
            return array_key_exists('active', $disasterResponseMode)
                && array_key_exists('expanded_radius_km', $disasterResponseMode)
                && array_key_exists('mass_notification', $disasterResponseMode);
        });
    }

    public function test_admin_live_emergency_dashboard_endpoint_returns_expected_counts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $hospitalUser = User::factory()->create(['role' => 'hospital']);
        $hospital = Hospital::create([
            'user_id' => $hospitalUser->id,
            'hospital_name' => 'Realtime Hospital',
            'location' => 'Lagos',
            'contact_person' => 'Dr Live',
            'contact_number' => '08083333333',
            'email' => $hospitalUser->email,
            'password' => 'password',
            'status' => 'approved',
        ]);

        $donorUser = User::factory()->create(['role' => 'donor']);
        $donor = Donor::create([
            'user_id' => $donorUser->id,
            'name' => $donorUser->name,
            'blood_type' => 'A+',
            'city' => 'Lagos',
            'contact_number' => '08084444444',
            'email' => $donorUser->email,
            'password' => 'password',
            'availability' => true,
            'privacy_consent_at' => now(),
        ]);

        $liveRequest = BloodRequest::create([
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
            'blood_type' => 'A+',
            'city' => 'Lagos',
            'requested_units' => 2,
            'status' => 'matching',
        ]);

        DonorAlertLog::create([
            'blood_request_id' => $liveRequest->id,
            'donor_id' => $donor->id,
            'escalation_level' => 1,
            'channel' => 'multi',
            'sent_at' => now(),
        ]);

        DonorRequestResponse::create([
            'donor_id' => $donor->id,
            'blood_request_id' => $liveRequest->id,
            'response' => 'accepted',
            'responded_at' => now(),
        ]);

        DonationHistory::create([
            'donor_id' => $donor->id,
            'hospital_id' => $hospital->id,
            'request_id' => $liveRequest->id,
            'donated_at' => now(),
            'donation_date' => now()->toDateString(),
            'location' => 'City Center',
            'units' => 1,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.emergency-dashboard.live'));

        $response->assertOk();
        $response->assertJsonPath('data.counts.live_blood_requests', 1);
        $response->assertJsonPath('data.counts.active_donor_alerts', 1);
        $response->assertJsonPath('data.counts.accepted_requests', 1);
        $response->assertJsonPath('data.counts.donations_completed', 1);
        $response->assertJsonCount(1, 'data.live_blood_requests');
        $response->assertJsonCount(1, 'data.active_donor_alerts');
    }

    public function test_admin_can_activate_and_deactivate_emergency_mode_from_web_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $activate = $this->actingAs($admin)->patch(route('admin.emergency-mode'), [
            'enabled' => true,
            'trigger' => 'mass casualty incidents',
        ]);

        $activate->assertRedirect();
        $activate->assertSessionHas('status', 'emergency-mode-activated');

        $stateAfterActivation = app(EmergencyBroadcastModeService::class)->state();

        $this->assertTrue($stateAfterActivation['enabled']);
        $this->assertSame('mass casualty incidents', $stateAfterActivation['trigger']);
        $this->assertSame($admin->id, $stateAfterActivation['activated_by']);

        $deactivate = $this->actingAs($admin)->patch(route('admin.emergency-mode'), [
            'enabled' => false,
        ]);

        $deactivate->assertRedirect();
        $deactivate->assertSessionHas('status', 'emergency-mode-deactivated');

        $stateAfterDeactivation = app(EmergencyBroadcastModeService::class)->state();

        $this->assertFalse($stateAfterDeactivation['enabled']);
        $this->assertSame('mass casualty incidents', $stateAfterDeactivation['trigger']);
    }

    public function test_admin_api_live_emergency_dashboard_endpoint_returns_expected_counts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $hospitalUser = User::factory()->create(['role' => 'hospital']);
        $hospital = Hospital::create([
            'user_id' => $hospitalUser->id,
            'hospital_name' => 'API Realtime Hospital',
            'location' => 'Lagos',
            'contact_person' => 'Dr API Live',
            'contact_number' => '08085555555',
            'email' => $hospitalUser->email,
            'password' => 'password',
            'status' => 'approved',
        ]);

        $donorUser = User::factory()->create(['role' => 'donor']);
        $donor = Donor::create([
            'user_id' => $donorUser->id,
            'name' => $donorUser->name,
            'blood_type' => 'O+',
            'city' => 'Lagos',
            'contact_number' => '08086666666',
            'email' => $donorUser->email,
            'password' => 'password',
            'availability' => true,
            'privacy_consent_at' => now(),
        ]);

        $liveRequest = BloodRequest::create([
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
            'blood_type' => 'O+',
            'city' => 'Lagos',
            'requested_units' => 1,
            'status' => 'pending',
        ]);

        DonorAlertLog::create([
            'blood_request_id' => $liveRequest->id,
            'donor_id' => $donor->id,
            'escalation_level' => 1,
            'channel' => 'multi',
            'sent_at' => now(),
        ]);

        DonorRequestResponse::create([
            'donor_id' => $donor->id,
            'blood_request_id' => $liveRequest->id,
            'response' => 'accepted',
            'responded_at' => now(),
        ]);

        DonationHistory::create([
            'donor_id' => $donor->id,
            'hospital_id' => $hospital->id,
            'request_id' => $liveRequest->id,
            'donated_at' => now(),
            'donation_date' => now()->toDateString(),
            'location' => 'City Center',
            'units' => 1,
            'status' => 'completed',
        ]);

        $response = $this->getJson('/api/admin/emergency-dashboard/live?limit=5');

        $response->assertOk();
        $response->assertJsonPath('data.counts.live_blood_requests', 1);
        $response->assertJsonPath('data.counts.active_donor_alerts', 1);
        $response->assertJsonPath('data.counts.accepted_requests', 1);
        $response->assertJsonPath('data.counts.donations_completed', 1);
    }

    public function test_admin_can_fetch_recent_past_match_request_options(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        BloodRequest::create([
            'case_id' => 'BR-TEST-1001',
            'hospital_name' => 'Cardinal Santos',
            'blood_type' => 'A+',
            'units_required' => 2,
            'urgency_level' => 'critical',
            'city' => 'San Juan',
            'status' => 'matching',
        ]);

        BloodRequest::create([
            'case_id' => 'BR-TEST-1002',
            'hospital_name' => 'Mercy Medical',
            'blood_type' => 'O-',
            'units_required' => 1,
            'urgency_level' => 'high',
            'city' => 'Pasig',
            'status' => 'pending',
        ]);

        $response = $this->getJson('/api/admin/past-match/requests?search=Cardinal');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.case_id', 'BR-TEST-1001');
        $response->assertJsonPath('data.0.hospital_name', 'Cardinal Santos');
    }

    public function test_admin_can_fetch_past_match_monitoring_payload(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $hospitalUser = User::factory()->create(['role' => 'hospital']);
        $hospital = Hospital::create([
            'user_id' => $hospitalUser->id,
            'hospital_name' => 'Realtime Match Hospital',
            'location' => 'Lagos',
            'contact_person' => 'Dr Match',
            'contact_number' => '08087777777',
            'email' => $hospitalUser->email,
            'password' => 'password',
            'status' => 'approved',
        ]);

        $donorUserA = User::factory()->create(['role' => 'donor']);
        $donorA = Donor::create([
            'user_id' => $donorUserA->id,
            'name' => 'Monitor Donor A',
            'blood_type' => 'O+',
            'city' => 'Lagos',
            'contact_number' => '08088888881',
            'email' => $donorUserA->email,
            'password' => 'password',
            'availability' => true,
            'reliability_score' => 85,
            'last_donation_date' => now()->subDays(120)->toDateString(),
            'privacy_consent_at' => now(),
        ]);

        $donorUserB = User::factory()->create(['role' => 'donor']);
        $donorB = Donor::create([
            'user_id' => $donorUserB->id,
            'name' => 'Monitor Donor B',
            'blood_type' => 'O+',
            'city' => 'Lagos',
            'contact_number' => '08088888882',
            'email' => $donorUserB->email,
            'password' => 'password',
            'availability' => true,
            'reliability_score' => 60,
            'last_donation_date' => now()->subDays(90)->toDateString(),
            'privacy_consent_at' => now(),
        ]);

        $bloodRequest = BloodRequest::create([
            'hospital_id' => $hospital->id,
            'case_id' => 'BR-PAST-9001',
            'hospital_name' => $hospital->hospital_name,
            'blood_type' => 'O+',
            'units_required' => 2,
            'urgency_level' => 'critical',
            'city' => 'Lagos',
            'distance_limit_km' => 50,
            'status' => 'matching',
            'notifications_sent' => 1,
            'responses_received' => 1,
        ]);

        RequestMatch::create([
            'blood_request_id' => $bloodRequest->id,
            'request_id' => $bloodRequest->id,
            'donor_id' => $donorA->id,
            'score' => 91.25,
            'response_status' => 'accepted',
            'rank' => 1,
        ]);

        RequestMatch::create([
            'blood_request_id' => $bloodRequest->id,
            'request_id' => $bloodRequest->id,
            'donor_id' => $donorB->id,
            'score' => 78.50,
            'response_status' => 'pending',
            'rank' => 2,
        ]);

        DonorAlertLog::create([
            'blood_request_id' => $bloodRequest->id,
            'donor_id' => $donorA->id,
            'escalation_level' => 1,
            'channel' => 'multi',
            'sent_at' => now()->subMinutes(4),
        ]);

        DonorAlertLog::create([
            'blood_request_id' => $bloodRequest->id,
            'donor_id' => $donorB->id,
            'escalation_level' => 2,
            'channel' => 'multi',
            'sent_at' => now()->subMinutes(2),
        ]);

        DonorRequestResponse::create([
            'donor_id' => $donorA->id,
            'blood_request_id' => $bloodRequest->id,
            'response' => 'accepted',
            'responded_at' => now()->subMinute(),
        ]);

        $response = $this->getJson('/api/admin/past-match/'.$bloodRequest->id);

        $response->assertOk();
        $response->assertJsonPath('data.request.request_id', 'BR-PAST-9001');
        $response->assertJsonPath('data.overview.notified_donors', 2);
        $response->assertJsonPath('data.overview.responded_donors', 1);
        $response->assertJsonPath('data.flow.current_stage', 'responses_received');
        $response->assertJsonPath('data.escalation.current_level', 2);
        $response->assertJsonPath('data.formula.profiles.medium.priority', 0.25);
        $response->assertJsonPath('data.formula.profiles.medium.time', 0.3);
        $response->assertJsonPath('data.matching_state.active_radius_km', 100);
        $response->assertJsonCount(2, 'data.ranked_donors');
        $response->assertJsonStructure([
            'data' => [
                'request' => ['request_id', 'hospital_name', 'blood_type', 'component', 'units_required', 'fulfilled_units', 'matching_status', 'time_remaining_seconds', 'urgency_level'],
                'overview' => ['total_donors_evaluated', 'eligible_donors', 'notified_donors', 'responded_donors'],
                'matching_state' => ['phase_label', 'matching_status', 'active_radius_km', 'total_donors_notified', 'response_rate_percentage', 'notifications_paused'],
                'ranked_donors' => [[
                    'rank',
                    'donor_id',
                    'donor_name',
                    'compatibility_score',
                    'operational_score',
                    'response_status',
                    'reliability_score',
                    'score_breakdown' => ['priority', 'availability', 'distance', 'time', 'final'],
                ]],
                'timeline',
                'flow',
                'escalation' => ['current_level', 'levels'],
                'escalation_timeline',
                'controls' => ['notifications_paused'],
                'analytics' => ['response_rate_series', 'donor_engagement', 'matching_efficiency'],
                'activity_feed',
                'formula' => ['expression', 'weights', 'active_profile', 'profiles'],
                'meta' => ['last_updated', 'auto_refresh_seconds', 'sync_status'],
            ],
        ]);
    }

    public function test_admin_can_pause_past_match_notifications(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $bloodRequest = BloodRequest::create([
            'case_id' => 'BR-PAST-CTRL-1',
            'hospital_name' => 'Control Hospital',
            'blood_type' => 'A+',
            'units_required' => 1,
            'urgency_level' => 'high',
            'city' => 'Lagos',
            'status' => 'matching',
            'distance_limit_km' => 40,
        ]);

        $response = $this->postJson('/api/admin/past-match/'.$bloodRequest->id.'/control', [
            'action' => 'pause_notifications',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.controls.notifications_paused', true);
        $response->assertJsonPath('data.distance_limit_km', 40);

        $this->assertTrue(Cache::get('past-match:control:'.$bloodRequest->id)['notifications_paused']);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'past-match.notifications-paused',
        ]);
    }

    public function test_admin_can_fetch_notification_dashboard_payload(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $hospitalUser = User::factory()->create(['role' => 'hospital']);
        $hospital = Hospital::create([
            'user_id' => $hospitalUser->id,
            'hospital_name' => 'Realtime Notification Hospital',
            'location' => 'Lagos',
            'contact_person' => 'Dr Notify',
            'contact_number' => '08089999991',
            'email' => $hospitalUser->email,
            'password' => 'password',
            'status' => 'approved',
        ]);

        $donorUserA = User::factory()->create(['role' => 'donor']);
        $donorA = Donor::create([
            'user_id' => $donorUserA->id,
            'name' => 'Notification Donor A',
            'blood_type' => 'O+',
            'city' => 'Lagos',
            'contact_number' => '08089999992',
            'email' => $donorUserA->email,
            'password' => 'password',
            'availability' => true,
            'reliability_score' => 88,
            'privacy_consent_at' => now(),
        ]);

        $donorUserB = User::factory()->create(['role' => 'donor']);
        $donorB = Donor::create([
            'user_id' => $donorUserB->id,
            'name' => 'Notification Donor B',
            'blood_type' => 'O+',
            'city' => 'Lagos',
            'contact_number' => '08089999993',
            'email' => $donorUserB->email,
            'password' => 'password',
            'availability' => true,
            'reliability_score' => 52,
            'privacy_consent_at' => now(),
        ]);

        $bloodRequest = BloodRequest::create([
            'hospital_id' => $hospital->id,
            'case_id' => 'BR-NOTIFY-7001',
            'hospital_name' => $hospital->hospital_name,
            'blood_type' => 'O+',
            'component' => 'Platelets',
            'units_required' => 3,
            'fulfilled_units' => 1,
            'urgency_level' => 'critical',
            'city' => 'Lagos',
            'status' => 'matching',
        ]);

        DonorAlertLog::create([
            'blood_request_id' => $bloodRequest->id,
            'donor_id' => $donorA->id,
            'escalation_level' => 1,
            'channel' => 'sms',
            'sent_at' => now()->subMinutes(4),
        ]);

        DonorAlertLog::create([
            'blood_request_id' => $bloodRequest->id,
            'donor_id' => $donorB->id,
            'escalation_level' => 2,
            'channel' => 'push',
            'sent_at' => now()->subMinutes(3),
        ]);

        NotificationDelivery::create([
            'user_id' => $donorUserA->id,
            'type' => 'emergency_blood_request',
            'channel' => 'sms',
            'status' => 'sent',
            'response' => [
                'title' => 'Emergency Blood Request',
                'message' => 'Need O+ platelets urgently.',
                'payload' => [
                    'blood_request_id' => $bloodRequest->id,
                    'donor_id' => $donorA->id,
                ],
                'attempt' => 2,
            ],
            'sent_at' => now()->subMinutes(4),
        ]);

        NotificationDelivery::create([
            'user_id' => $donorUserB->id,
            'type' => 'emergency_blood_request',
            'channel' => 'push',
            'status' => 'failed',
            'response' => [
                'title' => 'Emergency Blood Request',
                'message' => 'Need O+ platelets urgently.',
                'payload' => [
                    'blood_request_id' => $bloodRequest->id,
                    'donor_id' => $donorB->id,
                ],
                'reason' => 'gateway_timeout',
            ],
            'sent_at' => now()->subMinutes(3),
        ]);

        DonorRequestResponse::create([
            'donor_id' => $donorA->id,
            'blood_request_id' => $bloodRequest->id,
            'response' => 'accepted',
            'responded_at' => now()->subMinutes(2),
        ]);

        $response = $this->getJson('/api/admin/notifications/'.$bloodRequest->id);

        $response->assertOk();
        $response->assertJsonPath('data.request.request_id', 'BR-NOTIFY-7001');
        $response->assertJsonPath('data.request.component', 'Platelets');
        $response->assertJsonPath('data.request.status', 'Escalated');
        $response->assertJsonPath('data.summary.accepted.count', 1);
        $response->assertJsonPath('data.summary.total_notifications_sent', 2);
        $response->assertJsonPath('data.notification_stream.0.failure_reason', 'gateway_timeout');
        $response->assertJsonPath('data.notification_stream.1.retry_attempts', 1);
        $response->assertJsonStructure([
            'data' => [
                'request' => ['request_id', 'blood_type', 'component', 'units_required', 'fulfilled_units', 'status', 'time_elapsed_human'],
                'summary' => ['accepted', 'declined', 'no_response', 'avg_response_time', 'total_notifications_sent', 'response_health'],
                'notification_stream' => [[
                    'donor_name',
                    'channel',
                    'message_preview',
                    'delivery_status',
                    'failure_reason',
                    'retry_attempts',
                    'response_status',
                    'timestamp',
                ]],
                'engagement_insights' => ['most_responsive_donors', 'least_responsive_donors', 'reliability_trend'],
                'escalation_triggers',
                'controls' => ['notifications_paused'],
                'analytics' => ['response_rate_over_time', 'notification_success_rate', 'channel_effectiveness'],
                'meta' => ['last_updated', 'auto_refresh_seconds', 'sync_status'],
            ],
        ]);
    }

    public function test_admin_can_cancel_pending_notifications_for_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $bloodRequest = BloodRequest::create([
            'case_id' => 'BR-NOTIFY-CTRL-1',
            'hospital_name' => 'Control Notifications Hospital',
            'blood_type' => 'A+',
            'component' => 'Whole Blood',
            'units_required' => 2,
            'urgency_level' => 'high',
            'city' => 'Lagos',
            'status' => 'matching',
        ]);

        $response = $this->postJson('/api/admin/notifications/'.$bloodRequest->id.'/control', [
            'action' => 'cancel_pending_notifications',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.controls.notifications_paused', true);

        $this->assertTrue(Cache::get('past-match:control:'.$bloodRequest->id)['notifications_paused']);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'notification.pending-cancelled',
        ]);
    }

    public function test_admin_can_fetch_operational_analytics_dashboard_payload(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $hospitalUserA = User::factory()->create(['role' => 'hospital']);
        $hospitalA = Hospital::create([
            'user_id' => $hospitalUserA->id,
            'hospital_name' => 'Operations Hospital',
            'location' => 'Cavite',
            'contact_person' => 'Dr Ops',
            'contact_number' => '08081112221',
            'email' => $hospitalUserA->email,
            'password' => 'password',
            'status' => 'approved',
        ]);

        $hospitalUserB = User::factory()->create(['role' => 'hospital']);
        $hospitalB = Hospital::create([
            'user_id' => $hospitalUserB->id,
            'hospital_name' => 'Overflow Hospital',
            'location' => 'Manila',
            'contact_person' => 'Dr Overflow',
            'contact_number' => '08081112222',
            'email' => $hospitalUserB->email,
            'password' => 'password',
            'status' => 'approved',
        ]);

        $donorUser = User::factory()->create(['role' => 'donor']);
        $donor = Donor::create([
            'user_id' => $donorUser->id,
            'name' => 'Analytics Donor',
            'blood_type' => 'A+',
            'city' => 'Cavite',
            'contact_number' => '08081112223',
            'email' => $donorUser->email,
            'password' => 'password',
            'availability' => true,
            'reliability_score' => 87,
            'privacy_consent_at' => now(),
        ]);

        $primaryRequest = BloodRequest::create([
            'hospital_id' => $hospitalA->id,
            'case_id' => 'BR-ANALYTICS-1001',
            'hospital_name' => $hospitalA->hospital_name,
            'blood_type' => 'A+',
            'component' => 'PRBC',
            'units_required' => 2,
            'urgency_level' => 'critical',
            'city' => 'Cavite',
            'province' => 'Cavite',
            'status' => 'fulfilled',
            'notifications_sent' => 4,
            'responses_received' => 2,
            'accepted_donors' => 1,
            'fulfilled_units' => 2,
        ]);

        $secondaryRequest = BloodRequest::create([
            'hospital_id' => $hospitalB->id,
            'case_id' => 'BR-ANALYTICS-1002',
            'hospital_name' => $hospitalB->hospital_name,
            'blood_type' => 'O-',
            'component' => 'Whole Blood',
            'units_required' => 3,
            'urgency_level' => 'high',
            'city' => 'Manila',
            'province' => 'Metro Manila',
            'status' => 'matching',
            'notifications_sent' => 3,
            'responses_received' => 0,
        ]);

        DB::table('blood_requests')->where('id', $primaryRequest->id)->update([
            'created_at' => now()->subMinutes(6),
            'updated_at' => now()->subMinutes(1),
        ]);

        DB::table('blood_requests')->where('id', $secondaryRequest->id)->update([
            'created_at' => now()->subMinutes(20),
            'updated_at' => now()->subMinutes(2),
        ]);

        RequestMatch::create([
            'blood_request_id' => $primaryRequest->id,
            'request_id' => $primaryRequest->id,
            'donor_id' => $donor->id,
            'score' => 92.4,
            'response_status' => 'accepted',
            'rank' => 1,
        ]);

        DB::table('matches')->where('blood_request_id', $primaryRequest->id)->update([
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        DonorRequestResponse::create([
            'donor_id' => $donor->id,
            'blood_request_id' => $primaryRequest->id,
            'response' => 'accepted',
            'responded_at' => now()->subMinutes(4),
        ]);

        ActivityLog::record($admin->id, 'past-match.notifications-paused', [
            'blood_request_id' => $secondaryRequest->id,
        ]);

        $response = $this->getJson('/api/admin/analytics?'.http_build_query([
            'range' => 'weekly',
            'hospital_id' => $hospitalA->id,
            'blood_type' => 'A+',
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.meta.request_count', 1);
        $response->assertJsonPath('data.filters.applied.hospital_id', $hospitalA->id);
        $response->assertJsonPath('data.filters.applied.blood_type', 'A+');
        $response->assertJsonPath('data.system_health.status', 'slowing');
        $response->assertJsonPath('data.kpis.successful_matches_rate.value', 100);
        $response->assertJsonStructure([
            'data' => [
                'filters' => ['applied', 'options' => ['blood_types', 'hospitals', 'urgency_levels']],
                'executive_summary' => ['headline', 'summary_lines', 'efficiency_score'],
                'kpis' => [
                    'average_match_time_seconds',
                    'donor_response_rate',
                    'successful_matches_rate',
                    'drop_off_rate',
                ],
                'system_health' => ['status', 'label', 'message'],
                'live_activity_feed',
                'algorithm_transparency' => [
                    'matching_speed_trend',
                    'success_rate_by_urgency',
                    'average_score_distribution',
                ],
                'insights' => [
                    'predictive',
                    'bottlenecks' => ['requests_with_no_matches', 'slow_response_clusters', 'regions_with_low_donor_density'],
                    'geographic_intelligence' => ['donor_distribution', 'underserved_areas'],
                ],
                'meta' => ['generated_at', 'range_label', 'request_count'],
            ],
        ]);
    }

    public function test_admin_can_persist_system_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->patchJson('/api/admin/settings', [
            'urgency_threshold' => 82,
            'notification_rule' => 'balanced',
            'weights' => [
                'priority' => 0.34,
                'availability' => 0.22,
                'distance' => 0.18,
                'time' => 0.26,
            ],
            'weight_profiles' => [
                'low' => [
                    'priority' => 0.18,
                    'availability' => 0.27,
                    'distance' => 0.31,
                    'time' => 0.24,
                ],
                'medium' => [
                    'priority' => 0.34,
                    'availability' => 0.22,
                    'distance' => 0.18,
                    'time' => 0.26,
                ],
                'high' => [
                    'priority' => 0.29,
                    'availability' => 0.17,
                    'distance' => 0.16,
                    'time' => 0.38,
                ],
                'critical' => [
                    'priority' => 0.32,
                    'availability' => 0.14,
                    'distance' => 0.12,
                    'time' => 0.42,
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.urgency_threshold', 82);
        $response->assertJsonPath('data.notification_rule', 'balanced');
        $response->assertJsonPath('data.past_match_weights.priority', 0.34);
        $response->assertJsonPath('data.past_match_weights.time', 0.26);
        $response->assertJsonPath('data.past_match_weight_profiles.low.distance', 0.31);
        $response->assertJsonPath('data.past_match_weight_profiles.critical.time', 0.42);

        $settings = SystemSetting::query()->find(1);

        $this->assertNotNull($settings);
        $this->assertSame(82, $settings->urgency_threshold);
        $this->assertSame('balanced', $settings->notification_rule);
        $this->assertSame($admin->id, $settings->updated_by);
        $this->assertSame(0.42, $settings->past_match_weight_profiles['critical']['time']);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'system.settings.updated',
        ]);

        $this->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonPath('data.urgency_threshold', 82)
            ->assertJsonPath('data.notification_rule', 'balanced')
            ->assertJsonPath('data.past_match_weights.priority', 0.34)
            ->assertJsonPath('data.past_match_weight_profiles.critical.time', 0.42);
    }

    public function test_past_match_monitoring_uses_configured_settings_weights(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        SystemSetting::query()->updateOrCreate(
            ['id' => 1],
            [
                'urgency_threshold' => 75,
                'notification_rule' => 'critical-only',
                'past_match_weights' => [
                    'priority' => 0.34,
                    'availability' => 0.22,
                    'distance' => 0.18,
                    'time' => 0.26,
                ],
                'past_match_weight_profiles' => [
                    'low' => ['priority' => 0.18, 'availability' => 0.27, 'distance' => 0.31, 'time' => 0.24],
                    'medium' => ['priority' => 0.34, 'availability' => 0.22, 'distance' => 0.18, 'time' => 0.26],
                    'high' => ['priority' => 0.29, 'availability' => 0.17, 'distance' => 0.16, 'time' => 0.38],
                    'critical' => ['priority' => 0.32, 'availability' => 0.14, 'distance' => 0.12, 'time' => 0.42],
                ],
                'updated_by' => $admin->id,
            ]
        );

        $hospitalUser = User::factory()->create(['role' => 'hospital']);
        $hospital = Hospital::create([
            'user_id' => $hospitalUser->id,
            'hospital_name' => 'Settings Impact Hospital',
            'location' => 'Lagos',
            'contact_person' => 'Dr Config',
            'contact_number' => '08087771111',
            'email' => $hospitalUser->email,
            'password' => 'password',
            'status' => 'approved',
        ]);

        $donorUser = User::factory()->create(['role' => 'donor']);
        $donor = Donor::create([
            'user_id' => $donorUser->id,
            'name' => 'Configured Donor',
            'blood_type' => 'O+',
            'city' => 'Lagos',
            'contact_number' => '08087771112',
            'email' => $donorUser->email,
            'password' => 'password',
            'availability' => true,
            'reliability_score' => 90,
            'last_donation_date' => now()->subDays(120)->toDateString(),
            'privacy_consent_at' => now(),
        ]);

        $bloodRequest = BloodRequest::create([
            'hospital_id' => $hospital->id,
            'case_id' => 'BR-SETTINGS-4001',
            'hospital_name' => $hospital->hospital_name,
            'blood_type' => 'O+',
            'units_required' => 1,
            'urgency_level' => 'critical',
            'city' => 'Lagos',
            'distance_limit_km' => 50,
            'status' => 'matching',
        ]);

        RequestMatch::create([
            'blood_request_id' => $bloodRequest->id,
            'request_id' => $bloodRequest->id,
            'donor_id' => $donor->id,
            'score' => 90,
            'response_status' => 'pending',
            'rank' => 1,
        ]);

        $response = $this->getJson('/api/admin/past-match/'.$bloodRequest->id);

        $response->assertOk();
        $response->assertJsonPath('data.formula.active_profile', 'critical');
        $response->assertJsonPath('data.formula.label', 'PAST-Match Base Audit Formula');
        $response->assertJsonPath('data.ranked_donors.0.operational_score', 90);
        $response->assertJsonPath('data.ranked_donors.0.emergency_adjustment', 0);

        $formula = $response->json('data.formula');
        $payload = $response->json('data.ranked_donors.0');

        $this->assertIsArray($formula);
        $this->assertIsArray($payload);
        $this->assertSame(0.32, $formula['weights']['priority']);
        $this->assertSame(0.42, $formula['weights']['time']);
        $this->assertSame($formula['weights'], $formula['profiles']['critical']);
        $this->assertGreaterThan(0, $payload['compatibility_score']);
        $this->assertLessThanOrEqual(100.0, $payload['operational_score']);
    }

    public function test_failed_login_attempt_is_recorded_for_audit_monitoring(): void
    {
        ActivityLog::query()->delete();

        User::factory()->create([
            'email' => 'audit-login@example.com',
            'password' => bcrypt('Password123!'),
            'role' => 'admin',
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'audit-login@example.com',
            'password' => 'WrongPassword!',
        ])->assertStatus(422);

        $log = ActivityLog::query()->latest()->first();

        $this->assertNotNull($log);
        $this->assertSame('auth.login.failed', $log->action);
        $this->assertSame('high', $log->details['severity']);
        $this->assertSame('failed', $log->details['status']);
        $this->assertSame('audit-login@example.com', $log->details['target_label']);
    }

    public function test_unauthorized_role_access_is_recorded_for_audit_monitoring(): void
    {
        ActivityLog::query()->delete();

        $donorUser = User::factory()->create(['role' => 'donor']);
        Sanctum::actingAs($donorUser);

        $this->getJson('/api/admin/dashboard')->assertStatus(403);

        $log = ActivityLog::query()
            ->where('action', 'security.unauthorized-role-access')
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('security.unauthorized-role-access', $log->action);
        $this->assertSame('blocked', $log->details['status']);
        $this->assertSame('/api/admin/dashboard', $log->details['path']);
        $this->assertSame('donor', $log->details['actor_role']);
    }

    public function test_admin_can_fetch_enriched_audit_logs_monitoring_payload(): void
    {
        ActivityLog::query()->delete();

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        ActivityLog::record($admin->id, 'auth.login.failed', [
            'email' => 'ops-admin@example.com',
            'target_label' => 'ops-admin@example.com',
            'ip' => '10.0.0.8',
            'severity' => 'high',
            'status' => 'failed',
            'category' => 'authentication',
        ]);

        ActivityLog::record($admin->id, 'security.unauthorized-role-access', [
            'path' => '/api/admin/users',
            'ip' => '10.0.0.9',
            'actor_role' => 'donor',
            'severity' => 'critical',
            'status' => 'blocked',
            'category' => 'access',
        ]);

        ActivityLog::record($admin->id, 'security.unauthorized-role-access', [
            'path' => '/api/admin/requests',
            'ip' => '10.0.0.10',
            'actor_role' => 'hospital',
            'severity' => 'critical',
            'status' => 'blocked',
            'category' => 'access',
        ]);

        ActivityLog::record($admin->id, 'donor.suspended', [
            'target_type' => 'donor',
            'target_id' => 88,
            'target_label' => 'Donor 88',
            'severity' => 'medium',
            'status' => 'success',
            'category' => 'admin',
        ]);

        $response = $this->getJson('/api/admin/logs?range=7d');

        $response->assertOk();
        $response->assertJsonPath('data.system_status.label', 'Investigating');
        $response->assertJsonPath('data.summary.total_events', 4);
        $response->assertJsonPath('data.summary.unauthorized_attempts', 2);
        $response->assertJsonPath('data.table_view.pagination.total', 4);
        $response->assertJsonFragment(['action' => 'donor.suspended']);
        $response->assertJsonFragment(['title' => 'Unauthorized Access Blocked']);
        $response->assertJsonStructure([
            'data' => [
                'system_status' => ['label', 'tone', 'message', 'last_event_at', 'open_alerts_count'],
                'summary' => ['total_events', 'critical_events', 'failed_actions', 'unauthorized_attempts', 'admin_overrides', 'severity_breakdown', 'category_breakdown'],
                'filters' => ['applied', 'options' => ['ranges', 'severities', 'statuses', 'categories', 'actor_roles', 'actions', 'ip_addresses']],
                'high_priority_alerts',
                'table_view' => [
                    'data' => [[
                        'id',
                        'action',
                        'title',
                        'description',
                        'severity',
                        'status',
                        'category',
                        'timestamp',
                        'actor' => ['id', 'name', 'email', 'role'],
                        'ip_address',
                        'method',
                        'path',
                        'http_status',
                        'target' => ['type', 'id', 'label'],
                        'details',
                    ]],
                    'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
                ],
                'timeline_view',
                'export' => ['available_formats', 'file_name', 'generated_at'],
                'live_updates' => ['enabled', 'poll_interval_seconds', 'last_updated'],
            ],
        ]);
    }

    public function test_admin_api_rejects_completion_without_accepted_donor(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $bloodRequest = BloodRequest::create([
            'hospital_name' => 'Audit General Hospital',
            'blood_type' => 'O-',
            'city' => 'Manila',
            'units_required' => 1,
            'quantity' => 1,
            'requested_units' => 1,
            'urgency_level' => 'critical',
            'status' => 'matching',
        ]);

        $response = $this->patchJson("/api/admin/requests/{$bloodRequest->id}", [
            'status' => 'completed',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Cannot mark request as completed or fulfilled before a donor accepts the request.');

        $this->assertDatabaseHas('blood_requests', [
            'id' => $bloodRequest->id,
            'status' => 'matching',
        ]);
    }
}

