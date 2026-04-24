<?php

namespace Tests\Feature;

use App\Models\BloodRequest;
use App\Models\DonationHistory;
use App\Models\Donor;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BloodSupplyForecastingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_includes_blood_supply_forecasting_and_shortage_alerts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $hospital = $this->makeHospital();
        $donor = $this->makeDonor();

        // A+ demand across months (higher earlier, lower later), with supply decreasing in recent window.
        $aPlusOld = $this->makeRequest($hospital, 'A+', 12, now()->subDays(55));
        $aPlusRecent = $this->makeRequest($hospital, 'A+', 6, now()->subDays(10));

        // O- recent demand with almost no supply => critical shortage.
        $oNegRecent = $this->makeRequest($hospital, 'O-', 10, now()->subDays(7));

        // Supply linked to request IDs.
        // Previous 30-day window has stronger A+ supply.
        $this->makeDonation($donor, $hospital, $aPlusOld, 8, now()->subDays(40));
        // Recent 30-day window has weaker A+ supply.
        $this->makeDonation($donor, $hospital, $aPlusRecent, 2, now()->subDays(4));

        // O- supply intentionally low to trigger critical shortage.
        $this->makeDonation($donor, $hospital, $oNegRecent, 1, now()->subDays(3));

        $response = $this->getJson('/api/admin/dashboard?forecast_months=6');

        $response->assertOk();
        $response->assertJsonStructure([
            'metrics',
            'forecasting' => [
                'blood_type_demand_trends',
                'monthly_donation_patterns',
                'shortage_prediction',
                'forecast_summary' => ['critical_shortages', 'warning_shortages', 'demand_types_tracked', 'alerts'],
            ],
        ]);

        $alerts = $response->json('forecasting.forecast_summary.alerts');
        $this->assertIsArray($alerts);

        $this->assertContains('A+ supply decreasing', $alerts);
        $this->assertContains('O- critical shortage', $alerts);

        $shortageRows = collect($response->json('forecasting.shortage_prediction'));
        $oNeg = $shortageRows->firstWhere('blood_type', 'O-');

        $this->assertNotNull($oNeg);
        $this->assertSame('critical', $oNeg['status']);
        $this->assertGreaterThan(0, $oNeg['predicted_shortage_units']);
    }

    private function makeHospital(): Hospital
    {
        $user = User::factory()->create(['role' => 'hospital']);

        return Hospital::create([
            'user_id' => $user->id,
            'hospital_name' => 'Forecast Hospital',
            'address' => 'Forecast Avenue',
            'location' => 'Manila',
            'contact_person' => 'Dr Forecast',
            'contact_number' => '09170000002',
            'email' => $user->email,
            'password' => 'Password123!',
            'status' => 'approved',
        ]);
    }

    private function makeDonor(): Donor
    {
        $user = User::factory()->create(['role' => 'donor']);

        return Donor::create([
            'user_id' => $user->id,
            'name' => 'Forecast Donor',
            'blood_type' => 'A+',
            'city' => 'Manila',
            'contact_number' => '09170000003',
            'email' => $user->email,
            'password' => 'Password123!',
            'availability' => true,
            'privacy_consent_at' => now(),
        ]);
    }

    private function makeRequest(Hospital $hospital, string $bloodType, int $units, \Carbon\Carbon $createdAt): BloodRequest
    {
        $request = BloodRequest::create([
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
            'blood_type' => $bloodType,
            'units_required' => $units,
            'quantity' => $units,
            'requested_units' => $units,
            'urgency_level' => 'high',
            'city' => 'Manila',
            'status' => 'pending',
        ]);

        $request->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $request;
    }

    private function makeDonation(Donor $donor, Hospital $hospital, BloodRequest $request, int $units, \Carbon\Carbon $date): void
    {
        DonationHistory::create([
            'donor_id' => $donor->id,
            'hospital_id' => $hospital->id,
            'request_id' => $request->id,
            'donated_at' => $date,
            'donation_date' => $date->toDateString(),
            'location' => 'Forecast Center',
            'units' => $units,
            'status' => 'completed',
        ]);
    }
}

