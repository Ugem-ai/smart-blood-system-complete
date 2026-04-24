<?php

namespace Tests\Feature;

use App\Models\DonationHistory;
use App\Models\Donor;
use App\Models\DonorRequestResponse;
use App\Models\Hospital;
use App\Models\RequestMatch;
use App\Models\User;
use App\Services\ReliabilityScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReliabilityScoreTest extends TestCase
{
    use RefreshDatabase;

    private ReliabilityScoreService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReliabilityScoreService::class);
    }

    // ------------------------------------------------------------------
    // ReliabilityScoreService::compute()
    // ------------------------------------------------------------------

    public function test_new_donor_with_no_history_gets_neutral_score(): void
    {
        $donor = $this->makeDonor();

        $score = $this->service->compute($donor);

        $this->assertEquals(50.0, $score, 'New donor with no matches should start at 50 (neutral).');
    }

    public function test_perfect_donor_scores_100(): void
    {
        $donor   = $this->makeDonor();
        $hospital = $this->makeHospital();

        for ($i = 0; $i < 2; $i++) {
            $request = $this->makeBloodRequest($hospital);

            RequestMatch::create([
                'blood_request_id' => $request->id,
                'request_id'       => $request->id,
                'donor_id'         => $donor->id,
                'score'            => 90,
                'response_status'  => 'accepted',
                'rank'             => $i + 1,
            ]);

            DonorRequestResponse::create([
                'donor_id'         => $donor->id,
                'blood_request_id' => $request->id,
                'response'         => 'accepted',
                'responded_at'     => now()->subMinutes(5),
            ]);

            DonationHistory::create([
                'donor_id'      => $donor->id,
                'hospital_id'   => $hospital->id,
                'request_id'    => $request->id,
                'donated_at'    => now(),
                'donation_date' => now()->toDateString(),
                'units'         => 1,
                'status'        => 'completed',
            ]);
        }

        $score = $this->service->compute($donor);

        $this->assertGreaterThanOrEqual(90, $score, 'Perfect donor should score ≥90.');
        $this->assertLessThanOrEqual(100, $score);
    }

    public function test_donor_who_never_responds_scores_low(): void
    {
        $donor    = $this->makeDonor();
        $hospital = $this->makeHospital();

        // 5 matches, zero responses, zero donations
        for ($i = 0; $i < 5; $i++) {
            $request = $this->makeBloodRequest($hospital);
            RequestMatch::create([
                'blood_request_id' => $request->id,
                'request_id'       => $request->id,
                'donor_id'         => $donor->id,
                'score'            => 60,
                'response_status'  => 'pending',
                'rank'             => $i + 1,
            ]);
        }

        $score = $this->service->compute($donor);

        // completion_rate=0, response_rate=0, speed neutral(0.5)→10 pts → 10/100
        $this->assertLessThanOrEqual(15, $score, 'Ghost donor should score ≤15.');
    }

    public function test_score_is_clamped_between_0_and_100(): void
    {
        $donor = $this->makeDonor();
        $score = $this->service->compute($donor);

        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    // ------------------------------------------------------------------
    // ReliabilityScoreService::update() persists the score
    // ------------------------------------------------------------------

    public function test_update_persists_score_to_database(): void
    {
        $donor = $this->makeDonor();
        $returned = $this->service->update($donor);

        $this->assertDatabaseHas('donors', [
            'id' => $donor->id,
        ]);

        $fresh = Donor::find($donor->id);
        $this->assertEquals($returned, (float) $fresh->reliability_score);
    }

    // ------------------------------------------------------------------
    // Donor model helpers
    // ------------------------------------------------------------------

    public function test_reliability_label_returns_correct_tier(): void
    {
        $donor = $this->makeDonor();

        $donor->forceFill(['reliability_score' => 85.00]);
        $this->assertEquals('Elite', $donor->reliabilityLabel());

        $donor->forceFill(['reliability_score' => 65.00]);
        $this->assertEquals('Reliable', $donor->reliabilityLabel());

        $donor->forceFill(['reliability_score' => 45.00]);
        $this->assertEquals('Moderate', $donor->reliabilityLabel());

        $donor->forceFill(['reliability_score' => 20.00]);
        $this->assertEquals('Unreliable', $donor->reliabilityLabel());
    }

    public function test_is_highly_reliable_returns_true_for_elite_and_reliable_tiers(): void
    {
        $donor = $this->makeDonor();

        $donor->forceFill(['reliability_score' => 90.00]);
        $this->assertTrue($donor->isHighlyReliable());

        $donor->forceFill(['reliability_score' => 60.00]);
        $this->assertTrue($donor->isHighlyReliable());

        $donor->forceFill(['reliability_score' => 59.99]);
        $this->assertFalse($donor->isHighlyReliable());
    }

    // ------------------------------------------------------------------
    // PastMatchService uses reliability score
    // ------------------------------------------------------------------

    public function test_high_reliability_donor_scores_higher_in_past_match(): void
    {
        $matchService = app(\App\Services\PastMatchService::class);

        $donorHigh = $this->makeDonor(['reliability_score' => 100.0]);
        $donorLow  = $this->makeDonor(['reliability_score' => 0.0]);

        $scoreHigh = $matchService->calculateMatchScore('A+', $donorHigh, 1.0);
        $scoreLow  = $matchService->calculateMatchScore('A+', $donorLow, 1.0);

        $this->assertGreaterThan($scoreLow, $scoreHigh,
            'Donor with higher reliability score should rank higher in matching.');
    }

    // ------------------------------------------------------------------
    // Artisan command
    // ------------------------------------------------------------------

    public function test_recalculate_scores_command_updates_all_donors(): void
    {
        $donors = collect(range(1, 3))->map(fn () => $this->makeDonor(['reliability_score' => 0]));

        $this->artisan('donor:recalculate-scores')->assertExitCode(0);

        $donors->each(function ($donor) {
            $fresh = Donor::find($donor->id);
            // New donors with no activity get 50 (neutral).
            $this->assertEquals(50.0, (float) $fresh->reliability_score);
        });
    }

    public function test_recalculate_scores_command_accepts_single_donor_option(): void
    {
        $donor = $this->makeDonor(['reliability_score' => 0]);

        $this->artisan("donor:recalculate-scores --donor={$donor->id}")
            ->assertExitCode(0);

        $this->assertEquals(50.0, (float) Donor::find($donor->id)->reliability_score);
    }

    // ------------------------------------------------------------------
    // Observer auto-update smoke test
    // ------------------------------------------------------------------

    public function test_observer_updates_score_after_donation_history_created(): void
    {
        $donor   = $this->makeDonor(['reliability_score' => 0]);
        $hospital = $this->makeHospital();
        $request  = $this->makeBloodRequest($hospital);

        RequestMatch::create([
            'blood_request_id' => $request->id,
            'request_id'       => $request->id,
            'donor_id'         => $donor->id,
            'score'            => 70,
            'response_status'  => 'accepted',
            'rank'             => 1,
        ]);

        DonationHistory::create([
            'donor_id'      => $donor->id,
            'hospital_id'   => $hospital->id,
            'request_id'    => $request->id,
            'donated_at'    => now(),
            'donation_date' => now()->toDateString(),
            'units'         => 1,
            'status'        => 'completed',
        ]);

        $fresh = Donor::find($donor->id);
        // Score should be > 0 now (at minimum the completion_rate component)
        $this->assertNotEquals(0, (float) $fresh->reliability_score,
            'Observer should have recalculated score after donation history was created.');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function makeDonor(array $overrides = []): Donor
    {
        $user = User::factory()->create(['role' => 'donor']);

        return Donor::create(array_merge([
            'user_id'           => $user->id,
            'name'              => 'Test Donor '.$user->id,
            'blood_type'        => 'A+',
            'city'              => 'Manila',
            'contact_number'    => '09171234567',
            'email'             => $user->email,
            'password'          => 'password',
            'availability'      => true,
            'privacy_consent_at' => now(),
            'reliability_score' => 50.0,
        ], $overrides));
    }

    private function makeHospital(): Hospital
    {
        $user = User::factory()->create(['role' => 'hospital']);

        return Hospital::create([
            'user_id'        => $user->id,
            'hospital_name'  => 'Test Hospital',
            'address'        => '123 Test St',
            'location'       => 'Manila',
            'contact_person' => 'Dr Test',
            'contact_number' => '09171234567',
            'email'          => $user->email,
            'password'       => 'Password123!',
            'status'         => 'approved',
        ]);
    }

    private function makeBloodRequest(Hospital $hospital): \App\Models\BloodRequest
    {
        return \App\Models\BloodRequest::create([
            'hospital_id'     => $hospital->id,
            'hospital_name'   => $hospital->hospital_name,
            'blood_type'      => 'A+',
            'units_required'  => 1,
            'quantity'        => 1,
            'requested_units' => 1,
            'urgency_level'   => 'high',
            'city'            => 'Manila',
            'status'          => 'open',
        ]);
    }
}

