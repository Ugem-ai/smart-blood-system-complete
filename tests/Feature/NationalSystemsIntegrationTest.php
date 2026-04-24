<?php

namespace Tests\Feature;

use App\Models\BloodRequest;
use App\Models\DonationHistory;
use App\Models\Donor;
use App\Models\DonorAlertLog;
use App\Models\DonorRequestResponse;
use App\Models\Hospital;
use App\Models\NationalPartnerSyncLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NationalSystemsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_configured_national_partners(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        config([
            'services.national_integrations.partners' => [
                'philippine_red_cross' => [
                    'label' => 'Philippine Red Cross',
                    'enabled' => false,
                    'endpoint' => null,
                    'token' => null,
                    'scope' => 'national',
                ],
            ],
        ]);

        $response = $this->getJson('/api/admin/national-integrations/partners');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.key', 'philippine_red_cross');
        $response->assertJsonPath('data.0.label', 'Philippine Red Cross');
        $response->assertJsonPath('data.0.enabled', false);
    }

    public function test_admin_can_sync_emergency_dashboard_to_enabled_partner(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        config([
            'services.national_integrations.timeout_seconds' => 10,
            'services.national_integrations.partners' => [
                'philippine_red_cross' => [
                    'label' => 'Philippine Red Cross',
                    'enabled' => true,
                    'endpoint' => 'https://prc.example.org/api/smart-blood/sync',
                    'token' => 'prc-token',
                    'scope' => 'national',
                ],
            ],
        ]);

        Http::fake([
            'https://prc.example.org/*' => Http::response(['received' => true], 200),
        ]);

        $this->seedEmergencyData();

        $response = $this->postJson('/api/admin/national-integrations/philippine_red_cross/sync-emergency?limit=5');

        $response->assertOk();
        $response->assertJsonPath('data.partner_key', 'philippine_red_cross');
        $response->assertJsonPath('data.status', 'success');
        $response->assertJsonPath('data.http_status', 200);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://prc.example.org/api/smart-blood/sync'
                && $request->hasHeader('Authorization', 'Bearer prc-token');
        });

        $this->assertDatabaseHas('national_partner_sync_logs', [
            'partner_key' => 'philippine_red_cross',
            'status' => 'success',
            'http_status' => 200,
        ]);
    }

    public function test_sync_returns_accepted_when_partner_is_disabled(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        config([
            'services.national_integrations.partners' => [
                'philippine_red_cross' => [
                    'label' => 'Philippine Red Cross',
                    'enabled' => false,
                    'endpoint' => 'https://prc.example.org/api/smart-blood/sync',
                    'token' => 'prc-token',
                    'scope' => 'national',
                ],
            ],
        ]);

        $response = $this->postJson('/api/admin/national-integrations/philippine_red_cross/sync-emergency');

        $response->assertStatus(202);
        $response->assertJsonPath('data.status', 'skipped');

        $this->assertDatabaseHas('national_partner_sync_logs', [
            'partner_key' => 'philippine_red_cross',
            'status' => 'skipped',
        ]);
    }

    private function seedEmergencyData(): void
    {
        $hospitalUser = User::factory()->create(['role' => 'hospital']);
        $hospital = Hospital::create([
            'user_id' => $hospitalUser->id,
            'hospital_name' => 'National Sync Hospital',
            'address' => 'National Avenue',
            'location' => 'Manila',
            'contact_person' => 'Dr National',
            'contact_number' => '09179997777',
            'email' => $hospitalUser->email,
            'password' => 'Password123!',
            'status' => 'approved',
        ]);

        $donorUser = User::factory()->create(['role' => 'donor']);
        $donor = Donor::create([
            'user_id' => $donorUser->id,
            'name' => 'National Donor',
            'blood_type' => 'O+',
            'city' => 'Manila',
            'contact_number' => '09179998888',
            'email' => $donorUser->email,
            'password' => 'Password123!',
            'availability' => true,
            'privacy_consent_at' => now(),
        ]);

        $request = BloodRequest::create([
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
            'blood_type' => 'O+',
            'requested_units' => 1,
            'units_required' => 1,
            'quantity' => 1,
            'urgency_level' => 'high',
            'city' => 'Manila',
            'status' => 'matching',
        ]);

        DonorAlertLog::create([
            'blood_request_id' => $request->id,
            'donor_id' => $donor->id,
            'escalation_level' => 1,
            'channel' => 'multi',
            'sent_at' => now(),
        ]);

        DonorRequestResponse::create([
            'donor_id' => $donor->id,
            'blood_request_id' => $request->id,
            'response' => 'accepted',
            'responded_at' => now(),
        ]);

        DonationHistory::create([
            'donor_id' => $donor->id,
            'hospital_id' => $hospital->id,
            'request_id' => $request->id,
            'donated_at' => now(),
            'donation_date' => now()->toDateString(),
            'location' => 'National Avenue',
            'units' => 1,
            'status' => 'completed',
        ]);
    }
}
