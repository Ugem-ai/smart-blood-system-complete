<?php

namespace Tests\Feature;

use App\Models\BloodRequest;
use App\Models\DonationHistory;
use App\Models\Donor;
use App\Models\DonorRequestResponse;
use App\Models\Hospital;
use App\Models\RequestMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SystemEvaluationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluation_command_exports_results_report(): void
    {
        $hospitalUser = User::factory()->create(['role' => 'hospital']);
        $hospital = Hospital::create([
            'user_id' => $hospitalUser->id,
            'hospital_name' => 'Eval Hospital',
            'address' => 'Eval Address',
            'location' => 'Eval Address',
            'contact_person' => 'Dr Eval',
            'contact_number' => '09171111111',
            'email' => $hospitalUser->email,
            'password' => 'Password123!',
            'status' => 'approved',
        ]);

        $donorUser = User::factory()->create(['role' => 'donor']);
        $donor = Donor::create([
            'user_id' => $donorUser->id,
            'name' => 'Eval Donor',
            'blood_type' => 'A+',
            'city' => 'Manila',
            'contact_number' => '09172222222',
            'phone' => '09172222222',
            'email' => $donorUser->email,
            'password' => 'Password123!',
            'availability' => true,
            'privacy_consent_at' => now(),
        ]);

        $bloodRequest = BloodRequest::create([
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
            'blood_type' => 'A+',
            'units_required' => 1,
            'quantity' => 1,
            'requested_units' => 1,
            'urgency_level' => 'high',
            'city' => 'Manila',
            'status' => 'completed',
            'created_at' => now()->subMinutes(90),
            'updated_at' => now()->subMinutes(90),
        ]);

        RequestMatch::create([
            'blood_request_id' => $bloodRequest->id,
            'request_id' => $bloodRequest->id,
            'donor_id' => $donor->id,
            'score' => 90,
            'response_status' => 'accepted',
            'rank' => 1,
        ]);

        DonorRequestResponse::create([
            'donor_id' => $donor->id,
            'blood_request_id' => $bloodRequest->id,
            'response' => 'accepted',
            'responded_at' => now()->subMinutes(60),
        ]);

        DonationHistory::create([
            'donor_id' => $donor->id,
            'hospital_id' => $hospital->id,
            'request_id' => $bloodRequest->id,
            'donated_at' => now(),
            'donation_date' => now()->toDateString(),
            'location' => 'Eval Address',
            'units' => 1,
            'status' => 'completed',
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(30),
        ]);

        DB::table('system_uptime_samples')->insert([
            ['checked_at' => now()->subHours(4), 'status' => 'up', 'components' => json_encode(['database' => 'up', 'redis' => 'up']), 'created_at' => now(), 'updated_at' => now()],
            ['checked_at' => now()->subHours(3), 'status' => 'up', 'components' => json_encode(['database' => 'up', 'redis' => 'up']), 'created_at' => now(), 'updated_at' => now()],
            ['checked_at' => now()->subHours(2), 'status' => 'up', 'components' => json_encode(['database' => 'up', 'redis' => 'up']), 'created_at' => now(), 'updated_at' => now()],
            ['checked_at' => now()->subHours(1), 'status' => 'down', 'components' => json_encode(['database' => 'up', 'redis' => 'down']), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $exportPath = 'storage/app/evaluation/system-evaluation-test-report.md';

        $this->artisan('system:evaluate', [
            '--days' => 30,
            '--export' => $exportPath,
            '--json' => 1,
        ])
            ->expectsOutputToContain('System evaluation complete.')
            ->expectsOutputToContain('matching accuracy: 100%')
            ->expectsOutputToContain('donor response rate: 100%')
            ->expectsOutputToContain('system uptime: 75%')
            ->assertExitCode(0);

        $this->assertFileExists(base_path($exportPath));

        $report = file_get_contents(base_path($exportPath));
        $this->assertStringContainsString('matching accuracy: 100%', $report);
        $this->assertStringContainsString('donor response rate: 100%', $report);
        $this->assertStringContainsString('system uptime: 75%', $report);
    }
}

