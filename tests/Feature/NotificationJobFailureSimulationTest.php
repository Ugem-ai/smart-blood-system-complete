<?php

namespace Tests\Feature;

use App\Jobs\SendEmergencyNotificationsJob;
use App\Models\BloodRequest;
use App\Models\Donor;
use App\Models\Hospital;
use App\Models\User;
use App\Services\DonorCooldownService;
use App\Services\EmergencyEscalationService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class NotificationJobFailureSimulationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_job_records_failed_processing_metric_when_notification_service_throws(): void
    {
        Cache::forget('monitor:process:notifications:failure:count');

        $hospitalUser = User::factory()->create(['role' => 'hospital']);
        $hospital = Hospital::create([
            'user_id' => $hospitalUser->id,
            'hospital_name' => 'Failure Simulation Hospital',
            'address' => 'Queue Street',
            'location' => 'Manila',
            'contact_person' => 'Dr Failure',
            'contact_number' => '09179990051',
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
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'status' => 'matching',
        ]);

        $donorUser = User::factory()->create(['role' => 'donor']);
        Donor::create([
            'user_id' => $donorUser->id,
            'name' => 'Failure Donor',
            'blood_type' => 'A+',
            'city' => 'Manila',
            'contact_number' => '09170000999',
            'phone' => '09170000999',
            'email' => $donorUser->email,
            'password' => 'Password123!',
            'availability' => true,
            'privacy_consent_at' => now(),
            'reliability_score' => 75,
            'latitude' => 14.6000,
            'longitude' => 120.9842,
        ]);

        $notificationService = Mockery::mock(NotificationService::class);
        $notificationService
            ->shouldReceive('sendDonorAlert')
            ->once()
            ->andThrow(new RuntimeException('simulated notification failure'));

        $job = new SendEmergencyNotificationsJob($request->id, EmergencyEscalationService::LEVEL_CLOSEST);

        try {
            $job->handle(
                $notificationService,
                app(EmergencyEscalationService::class),
                app(DonorCooldownService::class),
                app(\App\Services\EmergencyBroadcastModeService::class),
                app(\App\Services\DonorFilterService::class),
                app(\App\Services\DonorNotificationTimingService::class)
            );

            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('simulated notification failure', $exception->getMessage());
        }

        $this->assertSame(1, (int) Cache::get('monitor:process:notifications:failure:count', 0));
    }
}