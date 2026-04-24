<?php

namespace Tests\Feature;

use App\Models\BloodRequest;
use App\Models\Donor;
use App\Models\Hospital;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotificationReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_falls_back_to_sms_when_push_delivery_fails(): void
    {
        config([
            'services.fcm.server_key' => 'test-fcm-key',
            'services.fcm.endpoint' => 'https://fcm.googleapis.com/fcm/send',
            'services.twilio.sid' => 'test-sid',
            'services.twilio.token' => 'test-token',
            'services.twilio.from' => '+15555550123',
        ]);

        Http::fake([
            'https://fcm.googleapis.com/*' => Http::response(['failure' => 1], 500),
            'https://api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201),
        ]);

        [$donor, $request] = $this->makeDonorAndRequest();

        app(NotificationService::class)->sendDonorAlert($donor, $request, 8.5);

        Http::assertSent(fn ($httpRequest) => str_contains($httpRequest->url(), 'fcm.googleapis.com'));
        Http::assertSent(fn ($httpRequest) => str_contains($httpRequest->url(), 'api.twilio.com'));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $donor->user_id,
            'type' => 'emergency_blood_request',
            'channel' => 'push',
            'status' => 'failed',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $donor->user_id,
            'type' => 'emergency_blood_request',
            'channel' => 'sms',
            'status' => 'sent',
        ]);
        $this->assertDatabaseMissing('activity_logs', [
            'action' => 'notification.delivery.escalated',
        ]);
    }

    public function test_sms_retries_until_successful_delivery(): void
    {
        config([
            'services.twilio.sid' => 'test-sid',
            'services.twilio.token' => 'test-token',
            'services.twilio.from' => '+15555550123',
            'services.notifications.sms_retry_attempts' => 3,
            'services.notifications.sms_retry_delay_ms' => 1,
        ]);

        $attempt = 0;

        Http::fake(function ($request) use (&$attempt) {
            $attempt++;

            return $attempt < 3
                ? Http::response(['message' => 'temporary failure'], 500)
                : Http::response(['sid' => 'SM123'], 201);
        });

        $user = User::factory()->create(['role' => 'donor']);

        $delivered = app(NotificationService::class)->sendSms(
            userId: $user->id,
            type: 'request_reminder',
            to: '+15555550000',
            message: 'Reminder message'
        );

        $this->assertTrue($delivered);
        Http::assertSentCount(3);
        $this->assertSame(3, NotificationDelivery::query()->where('channel', 'sms')->count());
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'request_reminder',
            'channel' => 'sms',
            'status' => 'sent',
        ]);
    }

    /**
     * @return array{0: Donor, 1: BloodRequest}
     */
    private function makeDonorAndRequest(): array
    {
        $donorUser = User::factory()->create(['role' => 'donor']);
        $hospitalUser = User::factory()->create(['role' => 'hospital']);

        $hospital = Hospital::create([
            'user_id' => $hospitalUser->id,
            'hospital_name' => 'Notification Hospital',
            'address' => 'Alert Street',
            'location' => 'Manila',
            'contact_person' => 'Dr Notify',
            'contact_number' => '09179990041',
            'email' => $hospitalUser->email,
            'password' => 'Password123!',
            'status' => 'approved',
        ]);

        $donor = Donor::create([
            'user_id' => $donorUser->id,
            'name' => 'Fallback Donor',
            'blood_type' => 'A+',
            'city' => 'Manila',
            'contact_number' => '09170000123',
            'phone' => '09170000123',
            'email' => $donorUser->email,
            'password' => 'Password123!',
            'availability' => true,
            'privacy_consent_at' => now(),
            'reliability_score' => 82,
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
        ]);

        return [$donor, $request];
    }
}