<?php

namespace Tests\Feature;

use App\Algorithms\PASTMatch;
use App\Jobs\ProcessBloodRequestMatchingJob;
use App\Jobs\SendEmergencyNotificationsJob;
use App\Models\BloodRequest;
use App\Models\Hospital;
use App\Models\RequestMatch;
use App\Models\User;
use App\Services\DonorFilterService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    private const MATCHING_TARGET_MS = 2000.0;
    private const API_TARGET_MS = 200.0;
    private const QUERY_TARGET_MS = 200.0;
    private const NOTIFICATION_TARGET_MS = 5000.0;

    private function matchingTargetMs(int $donorCount): float
    {
        if ($donorCount >= 10000) {
            return 3000.0;
        }

        return self::MATCHING_TARGET_MS;
    }

    private function notificationTargetMs(int $donorCount): float
    {
        if ($donorCount >= 10000) {
            return 10000.0;
        }

        return self::NOTIFICATION_TARGET_MS;
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function donorDatasetProvider(): array
    {
        return [
            '100 donors' => [100],
            '1000 donors' => [1000],
            '5000 donors' => [5000],
            '10000 donors' => [10000],
        ];
    }

    #[DataProvider('donorDatasetProvider')]
    public function test_performance_targets_are_met(int $donorCount): void
    {
        $this->seedDonorDataset($donorCount);

        $hospitalUser = User::factory()->create([
            'role' => 'hospital',
            'email' => 'perf-hospital-'.$donorCount.'@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $hospital = Hospital::create([
            'user_id' => $hospitalUser->id,
            'hospital_name' => 'Performance Hospital '.$donorCount,
            'address' => 'Performance City Center',
            'location' => 'Performance City Center',
            'latitude' => 14.5995000,
            'longitude' => 120.9842000,
            'contact_person' => 'Dr Performance',
            'contact_number' => '09170000000',
            'email' => $hospitalUser->email,
            'password' => 'Password123!',
            'status' => 'approved',
        ]);

        $filter = app(DonorFilterService::class);
        $algorithm = app(PASTMatch::class);

        $queryStart = microtime(true);
        $eligibleCount = DB::table('donors')
            ->whereIn('blood_type', $filter->compatibleDonorTypes('A+'))
            ->where('availability', true)
            ->where(function ($query) {
                $query->whereNull('last_donation_date')
                    ->orWhereDate('last_donation_date', '<=', now()->subDays(DonorFilterService::MIN_DONATION_INTERVAL_DAYS)->toDateString());
            })
            ->count();
        $queryMs = (microtime(true) - $queryStart) * 1000;

        // Warm-up to avoid first-hit cache/class-load noise in steady-state metrics.
        $filter->filterForRequest('A+', 14.5995, 120.9842, 25);

        $matchStart = microtime(true);
        $filteredDonors = $filter->filterForRequest('A+', 14.5995, 120.9842, 25);
        $rankedDonors = $algorithm->rankDonors($filteredDonors)->take(20)->values();
        $matchingMs = (microtime(true) - $matchStart) * 1000;

        $this->assertGreaterThan(0, $eligibleCount, 'Expected at least one eligible donor in dataset.');
        $this->assertGreaterThan(0, $rankedDonors->count(), 'Expected ranked donor set to be non-empty.');

        Queue::fake([ProcessBloodRequestMatchingJob::class]);
        Sanctum::actingAs($hospitalUser);

        // Warm-up monitor and request paths to remove first-call framework boot impact.
        $this->postJson('/api/v1/monitor/health');
        $this->postJson('/api/hospital/request', [
            'blood_type' => 'A+',
            'units_required' => 1,
            'urgency_level' => 'high',
            'city' => 'Performance City',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'distance_limit_km' => 25,
        ]);

        $apiStart = microtime(true);
        $apiResponse = $this->postJson('/api/hospital/request', [
            'blood_type' => 'A+',
            'units_required' => 1,
            'urgency_level' => 'high',
            'city' => 'Performance City',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'distance_limit_km' => 25,
        ]);
        $apiMs = (microtime(true) - $apiStart) * 1000;

        $apiResponse->assertCreated();
        Queue::assertPushed(ProcessBloodRequestMatchingJob::class);

        Http::fake([
            'https://fcm.googleapis.com/*' => Http::response(['success' => true], 200),
            'https://api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201),
            '*' => Http::response(['ok' => true], 200),
        ]);

        config([
            'services.fcm.server_key' => 'perf-fcm-key',
            'services.fcm.endpoint' => 'https://fcm.googleapis.com/fcm/send',
            'services.twilio.sid' => 'perf-sid',
            'services.twilio.token' => 'perf-token',
            'services.twilio.from' => '+15555550123',
            'services.notifications.max_burst' => 20,
        ]);

        $bloodRequest = BloodRequest::create([
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
            'blood_type' => 'A+',
            'units_required' => 1,
            'quantity' => 1,
            'requested_units' => 1,
            'urgency_level' => 'high',
            'city' => 'Performance City',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'status' => 'matching',
            'matched_donors' => $rankedDonors->map(fn (array $item) => $item['donor']->name)->all(),
        ]);

        foreach ($rankedDonors as $index => $item) {
            RequestMatch::create([
                'blood_request_id' => $bloodRequest->id,
                'request_id' => $bloodRequest->id,
                'donor_id' => $item['donor']->id,
                'score' => $item['score'],
                'response_status' => 'pending',
                'rank' => $index + 1,
            ]);
        }

        $notificationStart = microtime(true);
        $job = new SendEmergencyNotificationsJob($bloodRequest->id);
        $job->handle(
            app(NotificationService::class),
            app(\App\Services\EmergencyEscalationService::class),
            app(\App\Services\DonorCooldownService::class),
            app(\App\Services\EmergencyBroadcastModeService::class),
            app(\App\Services\DonorFilterService::class),
            app(\App\Services\DonorNotificationTimingService::class)
        );
        $notificationMs = (microtime(true) - $notificationStart) * 1000;

        // Assertions against Phase 23 targets.
        $matchingTargetMs = $this->matchingTargetMs($donorCount);

        $this->assertLessThan(
            $matchingTargetMs,
            $matchingMs,
            sprintf('[%d donors] Matching runtime %.2fms exceeded target %.2fms', $donorCount, $matchingMs, $matchingTargetMs)
        );

        $this->assertLessThan(
            self::API_TARGET_MS,
            $apiMs,
            sprintf('[%d donors] API response %.2fms exceeded target %.2fms', $donorCount, $apiMs, self::API_TARGET_MS)
        );

        $this->assertLessThan(
            self::QUERY_TARGET_MS,
            $queryMs,
            sprintf('[%d donors] Query time %.2fms exceeded target %.2fms', $donorCount, $queryMs, self::QUERY_TARGET_MS)
        );

        $notificationTargetMs = $this->notificationTargetMs($donorCount);

        $this->assertLessThan(
            $notificationTargetMs,
            $notificationMs,
            sprintf('[%d donors] Notification latency %.2fms exceeded target %.2fms', $donorCount, $notificationMs, $notificationTargetMs)
        );

        fwrite(STDOUT, sprintf(
            "\n[Performance][%d donors] query=%.2fms | matching=%.2fms | api=%.2fms | notifications=%.2fms\n",
            $donorCount,
            $queryMs,
            $matchingMs,
            $apiMs,
            $notificationMs
        ));
    }

    private function seedDonorDataset(int $count): void
    {
        $bloodTypes = ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'];
        $city = 'Performance City';
        $now = now();

        $userRows = [];
        for ($i = 1; $i <= $count; $i++) {
            $userRows[] = [
                'name' => 'Perf Donor '.$i,
                'email' => 'performance.donor.'.$count.'.'.$i.'@example.com',
                'password' => 'password123',
                'role' => 'donor',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($userRows, 1000) as $chunk) {
            DB::table('users')->insert($chunk);
        }

        $users = DB::table('users')
            ->select(['id', 'name', 'email'])
            ->where('email', 'like', 'performance.donor.'.$count.'.%@example.com')
            ->orderBy('id')
            ->get();

        $donorRows = [];

        $nearPoolSize = min(200, $count);

        foreach ($users as $index => $user) {
            $isNearPool = $index < $nearPoolSize;
            $offset = $isNearPool
                ? (($index % 40) - 20) / 10000
                : (($index % 4000) - 2000) / 1000; // near pool around center, far pool spread to +/-2.0 degrees
            $bloodType = $isNearPool ? 'A+' : $bloodTypes[$index % count($bloodTypes)];

            $donorRows[] = [
                'user_id' => $user->id,
                'name' => $user->name,
                'blood_type' => $bloodType,
                'city' => $city,
                'contact_number' => encrypt('09'.str_pad((string) ($index + 1), 9, '0', STR_PAD_LEFT)),
                'phone' => encrypt('09'.str_pad((string) ($index + 1), 9, '0', STR_PAD_LEFT)),
                'latitude' => 14.5995 + $offset,
                'longitude' => 120.9842 + $offset,
                'email' => $user->email,
                'password' => 'password123',
                'last_donation_date' => now()->subDays(90 + ($index % 120))->toDateString(),
                'availability' => true,
                'reliability_score' => 50 + ($index % 50),
                'privacy_consent_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($donorRows, 1000) as $chunk) {
            DB::table('donors')->insert($chunk);
        }
    }
}

