<?php

namespace Tests\Feature;

use App\Models\BloodRequest;
use App\Models\DonationHistory;
use App\Models\Donor;
use App\Models\DonorAlertLog;
use App\Models\DonorRequestResponse;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitoringOperationalMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_prometheus_metrics_expose_operational_kpis(): void
    {
        config(['services.monitoring.metrics_token' => 'monitoring-secret']);

        $donorUser1 = User::factory()->create(['role' => 'donor']);
        $donorUser2 = User::factory()->create(['role' => 'donor']);

        $donor1 = Donor::create([
            'user_id' => $donorUser1->id,
            'name' => 'Active Donor',
            'blood_type' => 'A+',
            'city' => 'Manila',
            'contact_number' => '09170000111',
            'phone' => '09170000111',
            'email' => $donorUser1->email,
            'password' => 'Password123!',
            'availability' => true,
            'reliability_score' => 80,
            'privacy_consent_at' => now(),
        ]);

        Donor::create([
            'user_id' => $donorUser2->id,
            'name' => 'Inactive Donor',
            'blood_type' => 'O+',
            'city' => 'Manila',
            'contact_number' => '09170000222',
            'phone' => '09170000222',
            'email' => $donorUser2->email,
            'password' => 'Password123!',
            'availability' => false,
            'reliability_score' => 60,
            'privacy_consent_at' => now(),
        ]);

        $hospitalUser = User::factory()->create(['role' => 'hospital']);
        $hospital = Hospital::create([
            'user_id' => $hospitalUser->id,
            'hospital_name' => 'Metrics Hospital',
            'address' => 'Metrics Street',
            'location' => 'Metrics Street',
            'contact_person' => 'Dr Metrics',
            'contact_number' => '09179999999',
            'email' => $hospitalUser->email,
            'password' => 'Password123!',
            'status' => 'approved',
        ]);

        $request = BloodRequest::create([
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
            'blood_type' => 'A+',
            'units_required' => 1,
            'quantity' => 1,
            'requested_units' => 1,
            'urgency_level' => 'high',
            'city' => 'Manila',
            'status' => 'matching',
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(30),
        ]);

        DonorRequestResponse::create([
            'donor_id' => $donor1->id,
            'blood_request_id' => $request->id,
            'response' => 'accepted',
            'responded_at' => now(),
        ]);

        DonorAlertLog::create([
            'blood_request_id' => $request->id,
            'donor_id' => $donor1->id,
            'escalation_level' => 1,
            'channel' => 'multi',
            'sent_at' => now(),
        ]);

        DonationHistory::create([
            'donor_id' => $donor1->id,
            'hospital_id' => $hospital->id,
            'request_id' => $request->id,
            'donated_at' => now(),
            'donation_date' => now()->toDateString(),
            'location' => 'Metrics Street',
            'units' => 1,
            'status' => 'completed',
        ]);

        $this->get('/api/v1/monitor/metrics')
            ->assertStatus(401);

        $this->get('/api/v1/monitor/metrics?token=monitoring-secret')
            ->assertStatus(401);

        $response = $this->get('/api/v1/monitor/metrics', [
            'X-Metrics-Token' => 'monitoring-secret',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('text/plain; version=0.0.4', (string) $response->headers->get('Content-Type'));

        $content = $response->getContent();

        $this->assertStringContainsString('smartblood_active_donors 1', $content);
        $this->assertStringContainsString('smartblood_daily_requests 1', $content);
        $this->assertStringContainsString('smartblood_successful_donations 1', $content);
        $this->assertMatchesRegularExpression('/smartblood_average_response_time_minutes\s+\d+(\.\d+)?/', $content);
        $this->assertStringContainsString('smartblood_emergency_live_blood_requests 1', $content);
        $this->assertStringContainsString('smartblood_emergency_active_donor_alerts 1', $content);
        $this->assertStringContainsString('smartblood_emergency_accepted_requests 1', $content);
        $this->assertStringContainsString('smartblood_emergency_donations_completed 1', $content);
    }
}

