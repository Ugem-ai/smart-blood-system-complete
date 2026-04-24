<?php

namespace Tests\Feature;

use App\Models\BloodRequest;
use App\Models\DonationHistory;
use App\Models\Donor;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SystemValidationPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_system_pipeline_is_validated_end_to_end(): void
    {
        config([
            'queue.default' => 'sync',
            'services.fcm.server_key' => 'test-fcm-key',
            'services.fcm.endpoint' => 'https://fcm.googleapis.com/fcm/send',
            'services.twilio.sid' => 'test-sid',
            'services.twilio.token' => 'test-token',
            'services.twilio.from' => '+15555550123',
        ]);

        Http::fake([
            'https://fcm.googleapis.com/*' => Http::response(['success' => true], 200),
            'https://api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201),
            '*' => Http::response(['ok' => true], 200),
        ]);

        // 1) Register donor (Authentication + Donor system).
        $donorRegister = $this->postJson('/api/v1/register', [
            'name' => 'Pipeline Donor',
            'email' => 'pipeline-donor@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'donor',
            'blood_type' => 'A+',
            'city' => 'Lagos',
            'contact_number' => '09000000001',
            'privacy_consent' => true,
        ]);

        $donorRegister->assertCreated();

        $donorUser = User::query()->where('email', 'pipeline-donor@example.com')->firstOrFail();
        $donor = Donor::query()->where('user_id', $donorUser->id)->firstOrFail();

        // 2) Register hospital (Hospital system).
        $hospitalRegister = $this->postJson('/api/hospital/register', [
            'hospital_name' => 'Pipeline General Hospital',
            'address' => 'Lagos Central',
            'latitude' => 6.5244,
            'longitude' => 3.3792,
            'contact_person' => 'Dr Pipeline',
            'contact_number' => '09000000002',
            'email' => 'pipeline-hospital@example.com',
            'hospital_registration_code' => 'test-hospital-code',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $hospitalRegister->assertCreated();

        $hospitalUser = User::query()->where('email', 'pipeline-hospital@example.com')->firstOrFail();
        $hospital = Hospital::query()->where('user_id', $hospitalUser->id)->firstOrFail();
        $this->assertSame('pending', $hospital->status);

        // Prepare admin account for approval + analytics checks.
        $admin = User::query()->create([
            'name' => 'Pipeline Admin',
            'email' => 'pipeline-admin@example.com',
            'password' => Hash::make('Password123!'),
            'role' => 'admin',
        ]);

        $adminLogin = $this->postJson('/api/v1/login', [
            'email' => 'pipeline-admin@example.com',
            'password' => 'Password123!',
        ]);

        $adminLogin->assertOk();
        // 3) Admin approves hospital (Admin dashboard module).
        Sanctum::actingAs($admin);

        $approve = $this
            ->patchJson('/api/admin/hospitals/'.$hospital->id.'/approve');

        $approve->assertOk();
        $hospital->refresh();
        $this->assertSame('approved', $hospital->status);

        // 4) Hospital logs in and creates blood request (Authentication + Blood request).
        $hospitalLogin = $this->postJson('/api/v1/login', [
            'email' => 'pipeline-hospital@example.com',
            'password' => 'Password123!',
        ]);

        $hospitalLogin->assertOk();
        Sanctum::actingAs($hospitalUser);

        $createRequest = $this
            ->postJson('/api/hospital/request', [
                'blood_type' => 'A+',
                'units_required' => 1,
                'urgency_level' => 'high',
                'city' => 'Lagos',
                'distance_limit_km' => 250,
            ]);

        $createRequest->assertCreated();
        $bloodRequestId = (int) $createRequest->json('data.id');

        // 5) Algorithm runs donor matching (PAST-Match).
        $bloodRequest = BloodRequest::query()->with('matches')->findOrFail($bloodRequestId);
        $this->assertSame('matching', $bloodRequest->status);

        $match = $bloodRequest->matches()->where('donor_id', $donor->id)->first();
        $this->assertNotNull($match, 'Expected donor to be matched by algorithm.');

        // 6) Notifications sent to donors (Notification system).
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://fcm.googleapis.com/'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.twilio.com'));

        // 7) Donor accepts request (Donor system response flow).
        $donorLogin = $this->postJson('/api/v1/login', [
            'email' => 'pipeline-donor@example.com',
            'password' => 'Password123!',
        ]);

        $donorLogin->assertOk();
        Sanctum::actingAs($donorUser);

        $accept = $this
            ->postJson('/api/donor/accept', [
                'blood_request_id' => $bloodRequest->id,
            ]);

        $accept->assertOk();
        $this->assertDatabaseHas('donor_request_responses', [
            'blood_request_id' => $bloodRequest->id,
            'donor_id' => $donor->id,
            'response' => 'accepted',
        ]);

        // 8) Hospital confirms donation (Hospital system completion flow).
        Sanctum::actingAs($hospitalUser);

        $confirmDonation = $this
            ->postJson('/api/hospital/confirm-donation', [
                'blood_request_id' => $bloodRequest->id,
                'donor_id' => $donor->id,
                'units' => 1,
            ]);

        $confirmDonation->assertOk();

        // 9) Donation recorded in DB (Donation tracking).
        $this->assertDatabaseHas('donation_histories', [
            'request_id' => $bloodRequest->id,
            'donor_id' => $donor->id,
            'hospital_id' => $hospital->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseCount('donation_histories', 1);

        // 10) Admin analytics updated (Admin dashboard analytics API).
        Sanctum::actingAs($admin);

        $dashboard = $this
            ->getJson('/api/admin/dashboard');

        $dashboard->assertOk();
        $dashboard->assertJsonPath('metrics.total_donors', 1);
        $this->assertEquals(100.0, (float) $dashboard->json('metrics.success_rate'));

        $this->assertSame(1, DonationHistory::query()->count());
    }
}

