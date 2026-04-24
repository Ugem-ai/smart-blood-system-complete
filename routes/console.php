<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use App\Models\BloodRequest;
use App\Models\DonationHistory;
use App\Models\Donor;
use App\Models\DonorRequestResponse;
use App\Models\Hospital;
use App\Models\RequestMatch;
use App\Models\User;
use App\Services\EmergencyBroadcastModeService;
use App\Services\PastMatchService;
use App\Services\ReliabilityScoreService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('queue:work:matching', function () {
    $this->info('Starting matching worker...');

    $this->call('queue:work', [
        '--queue' => 'matching',
        '--tries' => 5,
        '--backoff' => 10,
        '--timeout' => 120,
    ]);
})->purpose('Start dedicated matching queue worker');

Artisan::command('queue:work:notifications', function () {
    $this->info('Starting notification worker...');

    $this->call('queue:work', [
        '--queue' => 'notifications',
        '--tries' => 5,
        '--backoff' => 10,
        '--timeout' => 120,
    ]);
})->purpose('Start dedicated notification queue worker');

Artisan::command('system:expire-emergency-mode', function (EmergencyBroadcastModeService $emergencyBroadcastModeService) {
    $expired = $emergencyBroadcastModeService->expireExpiredState();

    $this->line($expired
        ? 'Expired emergency mode state was deactivated.'
        : 'No expired emergency mode state found.');
})->purpose('Expire emergency mode when its configured deadline has elapsed');

Artisan::command('system:load-test {--iterations=200} {--bloodType=A+} {--city=Unknown} {--limit=10}', function (PastMatchService $service) {
    $iterations = max(1, (int) $this->option('iterations'));
    $bloodType = (string) $this->option('bloodType');
    $city = (string) $this->option('city');
    $limit = max(1, (int) $this->option('limit'));

    $durations = [];
    $totalMatches = 0;

    $suiteStart = microtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        $start = microtime(true);
        $matches = $service->findTopDonors($bloodType, $city, $limit);
        $durations[] = (microtime(true) - $start) * 1000;
        $totalMatches += $matches->count();
    }

    sort($durations);
    $count = count($durations);
    $p95Index = max(0, (int) ceil($count * 0.95) - 1);
    $totalMs = (microtime(true) - $suiteStart) * 1000;

    $this->newLine();
    $this->info('Load test complete');
    $this->line('Iterations: '.$iterations);
    $this->line('Scenario: blood_type='.$bloodType.', city='.$city.', limit='.$limit);
    $this->line('Average latency: '.round(array_sum($durations) / $count, 2).' ms');
    $this->line('P95 latency: '.round($durations[$p95Index], 2).' ms');
    $this->line('Total matches returned: '.$totalMatches);
    $this->line('Total runtime: '.round($totalMs, 2).' ms');
})->purpose('Run synthetic load test for the matching engine');

Artisan::command('system:simulate-emergency {--requests=5} {--bloodType=O-} {--city=Unknown} {--limit=10} {--seed-if-empty=1}', function (PastMatchService $service) {
    $requests = max(1, (int) $this->option('requests'));
    $bloodType = (string) $this->option('bloodType');
    $city = (string) $this->option('city');
    $limit = max(1, (int) $this->option('limit'));
    $seedIfEmpty = (bool) ((int) $this->option('seed-if-empty'));

    DB::beginTransaction();

    $availableDonors = Donor::query()->where('availability', true)->count();

    if ($availableDonors === 0 && $seedIfEmpty) {
        $bloodTypes = ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'];

        for ($i = 1; $i <= 30; $i++) {
            $email = 'sim.donor.'.$i.'.'.now()->timestamp.'@example.com';

            $user = User::query()->create([
                'name' => 'Simulation Donor '.$i,
                'email' => $email,
                'password' => 'password123',
                'role' => 'donor',
            ]);

            Donor::query()->create([
                'user_id' => $user->id,
                'name' => 'Simulation Donor '.$i,
                'blood_type' => $bloodTypes[$i % count($bloodTypes)],
                'city' => $city,
                'contact_number' => '000000'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'email' => $email,
                'password' => 'password123',
                'availability' => true,
                'last_donation_date' => now()->subDays(90 + $i)->toDateString(),
                'reliability_score' => 60 + ($i % 40),
                'privacy_consent_at' => now(),
            ]);
        }

        $availableDonors = Donor::query()->where('availability', true)->count();
        $this->line('Temporary simulation donors seeded for this run.');
    }

    if ($availableDonors === 0) {
        $this->warn('No available donors found. Emergency simulation aborted.');
        DB::rollBack();
        return;
    }

    $unmatchedRequests = 0;
    $totalMatchedDonors = 0;
    $scores = [];

    $suiteStart = microtime(true);

    for ($i = 1; $i <= $requests; $i++) {
        $matches = $service->findTopDonors($bloodType, $city, $limit);

        if ($matches->isEmpty()) {
            $unmatchedRequests++;
            continue;
        }

        $totalMatchedDonors += $matches->count();
        $scores[] = $matches->first()['score'];
    }

    $runtimeMs = (microtime(true) - $suiteStart) * 1000;
    $servedRequests = $requests - $unmatchedRequests;

    $this->newLine();
    $this->info('Emergency simulation complete');
    $this->line('Simulated requests: '.$requests);
    $this->line('Served requests: '.$servedRequests);
    $this->line('Unserved requests: '.$unmatchedRequests);
    $this->line('Average matched donors/request: '.round($totalMatchedDonors / $requests, 2));
    $this->line('Average top score: '.round(count($scores) ? array_sum($scores) / count($scores) : 0, 2));
    $this->line('Runtime: '.round($runtimeMs, 2).' ms');

    DB::rollBack();
})->purpose('Simulate emergency request bursts against current donor pool');

Artisan::command('system:prepare-load-test {--hospitals=50} {--donors=10000} {--city=Metro Manila} {--password=Password123!}', function () {
    $hospitalCount = max(1, (int) $this->option('hospitals'));
    $donorCount = max(1, (int) $this->option('donors'));
    $city = (string) $this->option('city');
    $password = (string) $this->option('password');
    $hashedPassword = Hash::make($password);
    $now = now();

    $hospitalCredentials = ["email,password"];

    for ($i = 1; $i <= $hospitalCount; $i++) {
        $email = 'load.hospital.'.$i.'@example.com';

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Load Test Hospital '.$i,
                'password' => $hashedPassword,
                'role' => 'hospital',
            ]
        );

        Hospital::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'hospital_name' => 'Load Test Hospital '.$i,
                'address' => $city.' Zone '.(($i % 10) + 1),
                'location' => $city.' Zone '.(($i % 10) + 1),
                'latitude' => 14.5995 + (($i % 10) / 1000),
                'longitude' => 120.9842 + (($i % 10) / 1000),
                'contact_person' => 'Dr Load '.$i,
                'contact_number' => '0917'.str_pad((string) $i, 7, '0', STR_PAD_LEFT),
                'email' => $email,
                'password' => $password,
                'status' => 'approved',
            ]
        );

        $hospitalCredentials[] = $email.','.$password;
    }

    $existingDonorUsers = User::query()->where('email', 'like', 'load.donor.%@example.com')->count();

    if ($existingDonorUsers < $donorCount) {
        $bloodTypes = ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'];
        $userRows = [];

        for ($i = $existingDonorUsers + 1; $i <= $donorCount; $i++) {
            $userRows[] = [
                'name' => 'Load Test Donor '.$i,
                'email' => 'load.donor.'.$i.'@example.com',
                'password' => $hashedPassword,
                'role' => 'donor',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($userRows, 1000) as $chunk) {
            DB::table('users')->insert($chunk);
        }

        $newUsers = DB::table('users')
            ->select(['id', 'name', 'email'])
            ->where('email', 'like', 'load.donor.%@example.com')
            ->orderBy('id')
            ->get();

        $existingDonorIds = DB::table('donors')->pluck('user_id')->all();
        $existingMap = array_flip($existingDonorIds);
        $donorRows = [];

        foreach ($newUsers as $index => $user) {
            if (isset($existingMap[$user->id])) {
                continue;
            }

            $offset = (($index % 4000) - 2000) / 1000;
            $donorRows[] = [
                'user_id' => $user->id,
                'name' => $user->name,
                'blood_type' => $index < 300 ? 'A+' : $bloodTypes[$index % count($bloodTypes)],
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

    Storage::disk('local')->put('load-test/hospitals.csv', implode(PHP_EOL, $hospitalCredentials).PHP_EOL);

    $this->newLine();
    $this->info('Load-test dataset prepared.');
    $this->line('Approved hospitals: '.$hospitalCount);
    $this->line('Available donors target: '.$donorCount);
    $this->line('City: '.$city);
    $this->line('Credentials CSV: storage/app/load-test/hospitals.csv');
})->purpose('Seed approved hospitals and donor dataset for external load testing');

Artisan::command('system:record-uptime-sample', function () {
    $dbUp = true;
    $redisUp = true;

    try {
        DB::select('SELECT 1');
    } catch (\Throwable) {
        $dbUp = false;
    }

    try {
        Redis::connection()->ping();
    } catch (\Throwable) {
        $redisUp = false;
    }

    if (! $dbUp) {
        $this->error('Database is down; uptime sample cannot be persisted.');
        $this->line('Redis: '.($redisUp ? 'up' : 'down'));
        return 1;
    }

    $status = ($dbUp && $redisUp) ? 'up' : 'down';

    DB::table('system_uptime_samples')->insert([
        'checked_at' => now(),
        'status' => $status,
        'components' => json_encode([
            'database' => $dbUp ? 'up' : 'down',
            'redis' => $redisUp ? 'up' : 'down',
        ], JSON_UNESCAPED_SLASHES),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->info('Uptime sample recorded.');
    $this->line('Status: '.$status);
    $this->line('Database: '.($dbUp ? 'up' : 'down'));
    $this->line('Redis: '.($redisUp ? 'up' : 'down'));
})->purpose('Record one system uptime health sample (database and redis)');

Artisan::command('system:evaluate {--days=30} {--export=storage/app/evaluation/system-evaluation.md} {--json=0}', function () {
    $days = max(1, (int) $this->option('days'));
    $from = now()->subDays($days);
    $to = now();

    try {

    $requestsWithMatches = BloodRequest::query()
        ->whereBetween('created_at', [$from, $to])
        ->whereHas('matches')
        ->count();

    $requestsWithAcceptedResponse = BloodRequest::query()
        ->whereBetween('created_at', [$from, $to])
        ->whereHas('matches')
        ->whereHas('donorResponses', function ($query) {
            $query->where('response', 'accepted');
        })
        ->count();

    $matchingAccuracy = $requestsWithMatches > 0
        ? round(($requestsWithAcceptedResponse / $requestsWithMatches) * 100, 2)
        : 0.0;

    $contactedDonors = RequestMatch::query()
        ->whereHas('request', function ($query) use ($from, $to) {
            $query->whereBetween('created_at', [$from, $to]);
        })
        ->count();

    $donorResponses = DonorRequestResponse::query()
        ->whereNotNull('responded_at')
        ->whereBetween('responded_at', [$from, $to])
        ->count();

    $donorResponseRate = $contactedDonors > 0
        ? round(($donorResponses / $contactedDonors) * 100, 2)
        : 0.0;

    $fulfilledRequests = DonationHistory::query()
        ->where('status', 'completed')
        ->whereBetween('created_at', [$from, $to])
        ->count();

    $driver = DB::connection()->getDriverName();

    $avgFulfillmentMinutesRaw = $driver === 'sqlite'
        ? DonationHistory::query()
            ->join('blood_requests', 'blood_requests.id', '=', 'donation_histories.request_id')
            ->where('donation_histories.status', 'completed')
            ->whereBetween('donation_histories.created_at', [$from, $to])
            ->selectRaw('AVG((julianday(donation_histories.created_at) - julianday(blood_requests.created_at)) * 24 * 60) as avg_minutes')
            ->value('avg_minutes')
        : DonationHistory::query()
            ->join('blood_requests', 'blood_requests.id', '=', 'donation_histories.request_id')
            ->where('donation_histories.status', 'completed')
            ->whereBetween('donation_histories.created_at', [$from, $to])
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, blood_requests.created_at, donation_histories.created_at)) as avg_minutes')
            ->value('avg_minutes');

    $requestFulfillmentTimeMinutes = round((float) ($avgFulfillmentMinutesRaw ?? 0), 2);

    $totalUptimeSamples = DB::table('system_uptime_samples')
        ->whereBetween('checked_at', [$from, $to])
        ->count();

    $upUptimeSamples = DB::table('system_uptime_samples')
        ->whereBetween('checked_at', [$from, $to])
        ->where('status', 'up')
        ->count();

    if ($totalUptimeSamples === 0) {
        $dbUp = true;
        $redisUp = true;

        try {
            DB::select('SELECT 1');
        } catch (\Throwable) {
            $dbUp = false;
        }

        try {
            Redis::connection()->ping();
        } catch (\Throwable) {
            $redisUp = false;
        }

        $totalUptimeSamples = 1;
        $upUptimeSamples = ($dbUp && $redisUp) ? 1 : 0;
    }

    $systemUptime = round(($upUptimeSamples / $totalUptimeSamples) * 100, 2);

    $results = [
        'evaluation_window_days' => $days,
        'generated_at' => now()->toDateTimeString(),
        'matching_accuracy_percent' => $matchingAccuracy,
        'donor_response_rate_percent' => $donorResponseRate,
        'request_fulfillment_time_minutes' => $requestFulfillmentTimeMinutes,
        'system_uptime_percent' => $systemUptime,
        'supporting_counts' => [
            'requests_with_matches' => $requestsWithMatches,
            'requests_with_accepted_response' => $requestsWithAcceptedResponse,
            'contacted_donors' => $contactedDonors,
            'donor_responses' => $donorResponses,
            'fulfilled_requests' => $fulfilledRequests,
            'uptime_samples' => $totalUptimeSamples,
            'uptime_samples_up' => $upUptimeSamples,
        ],
    ];

    $exportRelativePath = trim((string) $this->option('export'));
    $exportAbsolutePath = base_path($exportRelativePath);
    $exportDirectory = dirname($exportAbsolutePath);

    if (! is_dir($exportDirectory)) {
        mkdir($exportDirectory, 0775, true);
    }

    $markdown = implode(PHP_EOL, [
        '# Final System Evaluation',
        '',
        'Generated at: '.$results['generated_at'],
        'Evaluation window: last '.$days.' days',
        '',
        '## Thesis Evaluation Metrics',
        '',
        '- matching accuracy: '.$matchingAccuracy.'%',
        '- donor response rate: '.$donorResponseRate.'%',
        '- request fulfillment time: '.$requestFulfillmentTimeMinutes.' minutes',
        '- system uptime: '.$systemUptime.'%',
        '',
        '## Metric Definitions',
        '',
        '- matching accuracy = requests with at least one accepted donor response / requests with generated matches',
        '- donor response rate = donor responses recorded / donor contacts (matches)',
        '- request fulfillment time = average minutes from request creation to completed donation record',
        '- system uptime = up health samples / total health samples',
        '',
        '## Supporting Counts',
        '',
        '- requests with matches: '.$requestsWithMatches,
        '- requests with accepted response: '.$requestsWithAcceptedResponse,
        '- contacted donors: '.$contactedDonors,
        '- donor responses: '.$donorResponses,
        '- fulfilled requests: '.$fulfilledRequests,
        '- uptime samples: '.$totalUptimeSamples,
        '- uptime samples (up): '.$upUptimeSamples,
        '',
    ]);

    file_put_contents($exportAbsolutePath, $markdown);

    $this->newLine();
    $this->info('System evaluation complete.');
    $this->line('matching accuracy: '.$matchingAccuracy.'%');
    $this->line('donor response rate: '.$donorResponseRate.'%');
    $this->line('request fulfillment time: '.$requestFulfillmentTimeMinutes.' minutes');
    $this->line('system uptime: '.$systemUptime.'%');
    $this->line('Report exported to: '.$exportRelativePath);

        if ((string) $this->option('json') === '1') {
            $this->line(json_encode($results, JSON_UNESCAPED_SLASHES));
        }
    } catch (\Throwable $e) {
        $this->error('System evaluation failed: '.$e->getMessage());
        $this->line('Ensure database and redis services are running, then retry.');
        return 1;
    }
})->purpose('Compute thesis evaluation metrics and export system evaluation report');

// ---------------------------------------------------------------------------
// Donor Reliability Scoring
// ---------------------------------------------------------------------------

Artisan::command('donor:recalculate-scores {--chunk=200} {--donor=}', function (ReliabilityScoreService $reliabilityService) {
    $specificId = $this->option('donor');
    $chunkSize  = max(1, (int) $this->option('chunk'));

    if ($specificId) {
        $donor = Donor::find((int) $specificId);
        if (! $donor) {
            $this->error("Donor #{$specificId} not found.");
            return 1;
        }
        $score = $reliabilityService->update($donor);
        $this->info("Donor #{$donor->id} ({$donor->name}) → score: {$score} ({$donor->reliabilityLabel()})");
        return 0;
    }

    $this->info("Recalculating reliability scores for all donors (chunk size: {$chunkSize})...");

    $bar   = $this->output->createProgressBar(Donor::count());
    $total = 0;

    Donor::chunk($chunkSize, function ($donors) use ($reliabilityService, $bar, &$total) {
        foreach ($donors as $donor) {
            if (! $donor instanceof Donor) {
                continue;
            }

            $reliabilityService->update($donor);
            $total++;
            $bar->advance();
        }
    });

    $bar->finish();
    $this->newLine(2);

    // Summary by tier
    $tiers = [
        'Elite'      => Donor::where('reliability_score', '>=', 80)->count(),
        'Reliable'   => Donor::whereBetween('reliability_score', [60, 79.99])->count(),
        'Moderate'   => Donor::whereBetween('reliability_score', [35, 59.99])->count(),
        'Unreliable' => Donor::where('reliability_score', '<', 35)->count(),
    ];

    $this->info("Done. {$total} donor(s) updated.");
    $this->table(['Tier', 'Donors'], collect($tiers)->map(fn ($c, $t) => [$t, $c])->values()->toArray());
})->purpose('Recalculate reliability scores for all donors (or one by --donor=ID)');
