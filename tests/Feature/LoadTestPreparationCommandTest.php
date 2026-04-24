<?php

namespace Tests\Feature;

use App\Models\Donor;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LoadTestPreparationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_load_test_preparation_command_seeds_dataset_and_exports_credentials(): void
    {
        Storage::fake('local');

        $this->artisan('system:prepare-load-test', [
            '--hospitals' => 3,
            '--donors' => 100,
            '--city' => 'Lagos',
            '--password' => 'Password123!',
        ])
            ->assertExitCode(0);

        $this->assertSame(3, Hospital::query()->count());
        $this->assertSame(100, Donor::query()->count());
        $this->assertSame(103, User::query()->count());
        $this->assertDatabaseHas('hospitals', [
            'hospital_name' => 'Load Test Hospital 1',
            'status' => 'approved',
        ]);

        Storage::disk('local')->assertExists('load-test/hospitals.csv');

        $csv = Storage::disk('local')->get('load-test/hospitals.csv');
        $this->assertStringContainsString('load.hospital.1@example.com,Password123!', $csv);
        $this->assertStringContainsString('load.hospital.3@example.com,Password123!', $csv);
    }
}

