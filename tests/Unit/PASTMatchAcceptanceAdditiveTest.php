<?php

namespace Tests\Unit;

use App\Algorithms\PASTMatch;
use App\Models\BloodRequest;
use App\Models\Donor;
use App\Models\DonorRequestAcceptance;
use App\Models\Hospital;
use App\Models\User;
use App\Services\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PASTMatchAcceptanceAdditiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(SystemSettingsService::class)->update([
            'urgency_threshold' => 70,
            'notification_rule' => 'critical-only',
            'past_match_weights' => [
                'compatibility' => 0.50,
                'availability' => 0.10,
                'travel_willingness' => 0.15,
                'distance_efficiency' => 0.25,
            ],
            'past_match_weight_profiles' => [
                'low' => [
                    'compatibility' => 0.50,
                    'availability' => 0.10,
                    'travel_willingness' => 0.15,
                    'distance_efficiency' => 0.25,
                ],
                'medium' => [
                    'compatibility' => 0.50,
                    'availability' => 0.10,
                    'travel_willingness' => 0.15,
                    'distance_efficiency' => 0.25,
                ],
                'high' => [
                    'compatibility' => 0.50,
                    'availability' => 0.10,
                    'travel_willingness' => 0.15,
                    'distance_efficiency' => 0.25,
                ],
                'critical' => [
                    'compatibility' => 0.50,
                    'availability' => 0.10,
                    'travel_willingness' => 0.15,
                    'distance_efficiency' => 0.25,
                ],
            ],
        ]);
    }

    #[DataProvider('backwardCompatibilityScenarios')]
    public function test_backward_compatibility_without_acceptance_record(
        bool $willingForEmergencyTravel,
        ?float $distanceKm,
        float $estimatedTravelMinutes
    ): void {
        [$donor, $bloodRequest] = $this->seedDonorAndRequest($willingForEmergencyTravel);

        $pastMatch = app(PASTMatch::class);
        $baseline = $pastMatch->rankDonors(collect([
            $this->candidateItem($donor, $distanceKm, $estimatedTravelMinutes),
        ]), [
            'urgency_level' => 'medium',
        ])->first();

        $rankedWithRequestContext = $pastMatch->rankDonors(collect([
            $this->candidateItem($donor, $distanceKm, $estimatedTravelMinutes),
        ]), [
            'urgency_level' => 'medium',
            'blood_request_id' => $bloodRequest->id,
        ])->first();

        $this->assertSame($baseline['factors']['compatibility'], $rankedWithRequestContext['factors']['compatibility']);
        $this->assertSame($baseline['factors']['availability'], $rankedWithRequestContext['factors']['availability']);
        $this->assertSame($baseline['factors']['travel_willingness'], $rankedWithRequestContext['factors']['travel_willingness']);
        $this->assertSame($baseline['factors']['distance_efficiency'], $rankedWithRequestContext['factors']['distance_efficiency']);
        $this->assertSame($baseline['factors']['chapter_preference'], $rankedWithRequestContext['factors']['chapter_preference']);
        $this->assertSame($baseline['audit_scores']['weights'], $rankedWithRequestContext['audit_scores']['weights']);
        $this->assertSame($baseline['base_score'], $rankedWithRequestContext['base_score']);
        $this->assertSame($baseline['operational_score'], $rankedWithRequestContext['operational_score']);
        $this->assertSame($baseline['score'], $rankedWithRequestContext['score']);
    }

    #[DataProvider('acceptedOverrideScenarios')]
    public function test_accepted_acceptance_overrides_travel_willingness_to_100(
        bool $willingForEmergencyTravel,
        ?float $distanceKm,
        float $estimatedTravelMinutes
    ): void {
        [$donor, $bloodRequest] = $this->seedDonorAndRequest($willingForEmergencyTravel);

        DonorRequestAcceptance::query()->create([
            'donor_id' => $donor->id,
            'blood_request_id' => $bloodRequest->id,
            'distance_km_at_acceptance' => $distanceKm,
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $ranked = app(PASTMatch::class)->rankDonors(collect([
            $this->candidateItem($donor, $distanceKm, $estimatedTravelMinutes),
        ]), [
            'urgency_level' => 'medium',
            'blood_request_id' => $bloodRequest->id,
        ]);

        $this->assertSame(100.0, $ranked->first()['factors']['travel_willingness']);
    }

    #[DataProvider('nonAcceptedStatuses')]
    public function test_pending_or_declined_acceptance_has_no_effect_on_fallback_logic(string $status): void
    {
        [$donor, $bloodRequest] = $this->seedDonorAndRequest(false);

        DonorRequestAcceptance::query()->create([
            'donor_id' => $donor->id,
            'blood_request_id' => $bloodRequest->id,
            'distance_km_at_acceptance' => 30.0,
            'status' => $status,
            'accepted_at' => null,
        ]);

        $pastMatch = app(PASTMatch::class);
        $baseline = $pastMatch->rankDonors(collect([
            $this->candidateItem($donor, 30.0, 45.0),
        ]), [
            'urgency_level' => 'medium',
        ])->first();

        $rankedWithNonAcceptedRecord = $pastMatch->rankDonors(collect([
            $this->candidateItem($donor, 30.0, 45.0),
        ]), [
            'urgency_level' => 'medium',
            'blood_request_id' => $bloodRequest->id,
        ])->first();

        $this->assertSame($baseline['factors']['travel_willingness'], $rankedWithNonAcceptedRecord['factors']['travel_willingness']);
        $this->assertSame($baseline['audit_scores']['weights'], $rankedWithNonAcceptedRecord['audit_scores']['weights']);
        $this->assertSame($baseline['base_score'], $rankedWithNonAcceptedRecord['base_score']);
        $this->assertSame($baseline['operational_score'], $rankedWithNonAcceptedRecord['operational_score']);
        $this->assertSame($baseline['score'], $rankedWithNonAcceptedRecord['score']);
    }

    /**
     * @return array<int, array{0: bool, 1: ?float, 2: float}>
     */
    public static function backwardCompatibilityScenarios(): array
    {
        return [
            'willing_flag_true' => [true, 30.0, 45.0],
            'willing_false_distance_30' => [false, 30.0, 45.0],
            'willing_false_distance_5' => [false, 5.0, 10.0],
        ];
    }

    /**
     * @return array<int, array{0: bool, 1: ?float, 2: float}>
     */
    public static function acceptedOverrideScenarios(): array
    {
        return [
            'accepted_far_distance_willing_false' => [false, 30.0, 45.0],
            'accepted_near_distance_willing_false' => [false, 5.0, 10.0],
            'accepted_null_distance_willing_false' => [false, null, 40.0],
            'accepted_far_distance_willing_true' => [true, 30.0, 45.0],
        ];
    }

    /**
     * @return array<int, array{0: string}>
     */
    public static function nonAcceptedStatuses(): array
    {
        return [
            'pending' => ['pending'],
            'declined' => ['declined'],
        ];
    }

    /**
     * @return array{0: Donor, 1: BloodRequest}
     */
    private function seedDonorAndRequest(bool $willingForEmergencyTravel): array
    {
        $hospitalUser = User::factory()->create(['role' => 'hospital']);
        $hospital = Hospital::query()->create([
            'user_id' => $hospitalUser->id,
            'hospital_name' => 'Score Verification Hospital',
            'location' => 'Manila',
            'contact_person' => 'Dr Verify',
            'contact_number' => '09170000011',
            'email' => $hospitalUser->email,
            'password' => 'password',
            'status' => 'approved',
        ]);

        $bloodRequest = BloodRequest::query()->create([
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
            'blood_type' => 'A+',
            'quantity' => 1,
            'requested_units' => 1,
            'urgency_level' => 'medium',
            'city' => 'Manila',
            'is_emergency' => true,
            'status' => 'matching',
        ]);

        $donorUser = User::factory()->create(['role' => 'donor']);
        $donor = Donor::query()->create([
            'user_id' => $donorUser->id,
            'name' => $donorUser->name,
            'blood_type' => 'A+',
            'city' => 'Manila',
            'contact_number' => '09170000022',
            'email' => $donorUser->email,
            'password' => 'password',
            'availability' => true,
            'privacy_consent_at' => now(),
            'last_donation_date' => now()->subDays(120)->toDateString(),
            'reliability_score' => 60,
            'normal_travel_radius' => 5,
            'willing_for_emergency_travel' => $willingForEmergencyTravel,
        ]);

        return [$donor, $bloodRequest];
    }

    /**
     * @return array{donor: Donor, distance_km: ?float, estimated_travel_minutes: float, traffic_condition: string, traffic_multiplier: float, transport_accessibility_score: float, fastest_arrival_score: float}
     */
    private function candidateItem(Donor $donor, ?float $distanceKm, float $estimatedTravelMinutes): array
    {
        return [
            'donor' => $donor,
            'distance_km' => $distanceKm,
            'estimated_travel_minutes' => $estimatedTravelMinutes,
            'traffic_condition' => 'moderate',
            'traffic_multiplier' => 1.2,
            'transport_accessibility_score' => 60.0,
            'fastest_arrival_score' => 55.0,
        ];
    }

}
