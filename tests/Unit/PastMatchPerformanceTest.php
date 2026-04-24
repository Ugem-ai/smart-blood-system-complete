<?php

namespace Tests\Unit;

use App\Services\PastMatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PastMatchPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_past_match_p95_latency_is_within_threshold(): void
    {
        $this->seedBenchmarkDataset(400);

        $service = app(PastMatchService::class);

        // Warm-up run reduces first-hit noise from caches and class loading.
        $service->findTopDonors('A+', 'Lagos', 10);

        $iterations = 60;
        $durationsMs = [];

        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $matches = $service->findTopDonors('A+', 'Lagos', 10);
            $durationsMs[] = (microtime(true) - $start) * 1000;

            $this->assertGreaterThan(0, $matches->count(), 'Benchmark dataset should produce at least one candidate.');
        }

        sort($durationsMs);

        $p95 = $this->percentile($durationsMs, 0.95);
        $avg = array_sum($durationsMs) / count($durationsMs);
        $thresholdMs = (float) env('PASTMATCH_BENCHMARK_P95_MS', 150.0);

        $this->assertLessThanOrEqual(
            $thresholdMs,
            $p95,
            sprintf('PASTMatch latency regression detected. avg=%.2fms, p95=%.2fms, threshold=%.2fms', $avg, $p95, $thresholdMs)
        );
    }

    private function seedBenchmarkDataset(int $count): void
    {
        $bloodTypes = ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'];
        $cities = ['Lagos', 'Abuja', 'Kano', 'Cebu', 'Manila'];

        $now = now();
        $userRows = [];

        for ($i = 1; $i <= $count; $i++) {
            $userRows[] = [
                'name' => 'Perf Donor '.$i,
                'email' => 'perf.donor.'.$i.'@example.com',
                'password' => 'benchmark-password',
                'role' => 'donor',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('users')->insert($userRows);

        $users = DB::table('users')
            ->select(['id', 'email', 'name'])
            ->where('email', 'like', 'perf.donor.%@example.com')
            ->orderBy('id')
            ->get();

        $donorRows = [];

        foreach ($users as $index => $user) {
            $donorRows[] = [
                'user_id' => $user->id,
                'name' => $user->name,
                'blood_type' => $bloodTypes[$index % count($bloodTypes)],
                'city' => $cities[$index % count($cities)],
                'contact_number' => '0900'.str_pad((string) ($index + 1), 8, '0', STR_PAD_LEFT),
                'phone' => '0900'.str_pad((string) ($index + 1), 8, '0', STR_PAD_LEFT),
                'email' => $user->email,
                'password' => 'benchmark-password',
                'last_donation_date' => now()->subDays(90 + ($index % 60))->toDateString(),
                'availability' => true,
                'reliability_score' => 60 + ($index % 40),
                'privacy_consent_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('donors')->insert($donorRows);
    }

    /**
     * @param array<int, float> $sortedValues
     */
    private function percentile(array $sortedValues, float $p): float
    {
        $count = count($sortedValues);
        if ($count === 0) {
            return 0.0;
        }

        $index = (int) ceil($count * $p) - 1;
        $index = max(0, min($count - 1, $index));

        return (float) $sortedValues[$index];
    }
}
