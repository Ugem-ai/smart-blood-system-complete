<?php

namespace Tests\Feature;

use App\Models\BloodRequest;
use App\Models\Hospital;
use App\Models\RequestMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BloodRequestApiTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function approvedHospitalUser(string $prefix = 'hosp'): array
    {
        $user = User::factory()->create(['role' => 'hospital', 'email' => "{$prefix}@example.com"]);

        $hospital = Hospital::create([
            'user_id'       => $user->id,
            'hospital_name' => strtoupper($prefix) . ' Medical Center',
            'location'      => 'Manila',
            'contact_person'=> 'Dr. Test',
            'contact_number'=> '09179990099',
            'email'         => $user->email,
            'password'      => 'Password123!',
            'status'        => 'approved',
        ]);

        return [$user, $hospital];
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'blood_type'        => 'A+',
            'units_required'    => 2,
            'urgency_level'     => 'medium',
            'city'              => 'Manila',
            'distance_limit_km' => 50,
            'required_on'       => now()->addDays(3)->toDateString(),
        ], $overrides);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/hospital/requests — index envelope
    // ─────────────────────────────────────────────────────────────────────────

    public function test_index_returns_success_envelope_with_paginated_data(): void
    {
        [$user, $hospital] = $this->approvedHospitalUser('indextest');

        BloodRequest::create([
            'hospital_id'   => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
            'blood_type'    => 'O+',
            'units_required'=> 1,
            'quantity'      => 1,
            'requested_units'=> 1,
            'urgency_level' => 'low',
            'city'          => 'Manila',
            'status'        => 'pending',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/hospital/requests');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Blood requests retrieved successfully.')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data',
                    'current_page',
                    'total',
                    'per_page',
                ],
            ]);

        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_index_filters_by_status(): void
    {
        [$user, $hospital] = $this->approvedHospitalUser('statusfilter');

        foreach (['pending', 'pending', 'matching'] as $i => $status) {
            BloodRequest::create([
                'hospital_id'   => $hospital->id,
                'hospital_name' => $hospital->hospital_name,
                'blood_type'    => 'O+',
                'units_required'=> 1,
                'quantity'      => 1,
                'requested_units'=> 1,
                'urgency_level' => 'low',
                'city'          => 'Manila',
                'status'        => $status,
            ]);
        }

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/hospital/requests?status=pending');

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertCount(2, $response->json('data.data'));
    }

    public function test_index_filters_by_is_emergency(): void
    {
        [$user, $hospital] = $this->approvedHospitalUser('emerfilter');

        BloodRequest::create([
            'hospital_id'    => $hospital->id,
            'hospital_name'  => $hospital->hospital_name,
            'blood_type'     => 'B+',
            'units_required' => 1,
            'quantity'       => 1,
            'requested_units'=> 1,
            'urgency_level'  => 'high',
            'city'           => 'Manila',
            'status'         => 'pending',
            'is_emergency'   => true,
        ]);

        BloodRequest::create([
            'hospital_id'    => $hospital->id,
            'hospital_name'  => $hospital->hospital_name,
            'blood_type'     => 'A+',
            'units_required' => 1,
            'quantity'       => 1,
            'requested_units'=> 1,
            'urgency_level'  => 'low',
            'city'           => 'Manila',
            'status'         => 'pending',
            'is_emergency'   => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/hospital/requests?is_emergency=1');

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertTrue($response->json('data.data.0.is_emergency'));
    }

    public function test_index_filters_by_component(): void
    {
        [$user, $hospital] = $this->approvedHospitalUser('compfilter');

        foreach (['PRBC', 'Platelets', 'PRBC'] as $component) {
            BloodRequest::create([
                'hospital_id'    => $hospital->id,
                'hospital_name'  => $hospital->hospital_name,
                'blood_type'     => 'O+',
                'units_required' => 1,
                'quantity'       => 1,
                'requested_units'=> 1,
                'urgency_level'  => 'medium',
                'city'           => 'Manila',
                'status'         => 'pending',
                'component'      => $component,
            ]);
        }

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/hospital/requests?component=PRBC');
        $response->assertOk();
        $this->assertCount(2, $response->json('data.data'));
    }

    public function test_index_returns_404_when_hospital_profile_is_missing(): void
    {
        $user = User::factory()->create(['role' => 'hospital', 'email' => 'noprofile@example.com']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/hospital/requests');

        $response->assertNotFound()->assertJsonPath('success', false);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/hospital/requests/{id} — show envelope
    // ─────────────────────────────────────────────────────────────────────────

    public function test_show_returns_success_envelope_with_blood_request_and_matches(): void
    {
        [$user, $hospital] = $this->approvedHospitalUser('showhospital');

        $bloodRequest = BloodRequest::create([
            'hospital_id'    => $hospital->id,
            'hospital_name'  => $hospital->hospital_name,
            'blood_type'     => 'O-',
            'units_required' => 3,
            'quantity'       => 3,
            'requested_units'=> 3,
            'urgency_level'  => 'high',
            'city'           => 'Cebu',
            'status'         => 'matching',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/hospital/requests/{$bloodRequest->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Blood request retrieved successfully.')
            ->assertJsonPath('data.id', $bloodRequest->id)
            ->assertJsonPath('data.blood_type', 'O-')
            ->assertJsonPath('data.urgency_level', 'high')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'blood_type', 'urgency_level', 'city', 'status', 'hospital'],
            ]);
    }

    public function test_show_returns_403_when_request_belongs_to_different_hospital(): void
    {
        [$userA, $hospitalA] = $this->approvedHospitalUser('ownerhosp');
        [$userB, $hospitalB] = $this->approvedHospitalUser('otherhosp');

        $bloodRequest = BloodRequest::create([
            'hospital_id'    => $hospitalA->id,
            'hospital_name'  => $hospitalA->hospital_name,
            'blood_type'     => 'AB+',
            'units_required' => 1,
            'quantity'       => 1,
            'requested_units'=> 1,
            'urgency_level'  => 'low',
            'city'           => 'Manila',
            'status'         => 'pending',
        ]);

        Sanctum::actingAs($userB);

        $response = $this->getJson("/api/hospital/requests/{$bloodRequest->id}");

        $response->assertForbidden()->assertJsonPath('success', false);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/hospital/requests — store with new fields
    // ─────────────────────────────────────────────────────────────────────────

    public function test_store_persists_critical_urgency_without_remapping(): void
    {
        Queue::fake();

        [$user, $hospital] = $this->approvedHospitalUser('criticalhosp');

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/hospital/requests', $this->validPayload([
            'urgency_level' => 'critical',
        ]));

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.urgency_level', 'critical');

        $this->assertDatabaseHas('blood_requests', [
            'hospital_id'   => $hospital->id,
            'urgency_level' => 'critical',
        ]);
    }

    public function test_store_with_is_emergency_true_flags_request_and_returns_operational_mode(): void
    {
        Queue::fake();

        [$user, $hospital] = $this->approvedHospitalUser('emerhosp');

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/hospital/requests', $this->validPayload([
            'urgency_level' => 'medium',
            'is_emergency'  => true,
        ]));

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_emergency', true)
            ->assertJsonPath('operational_mode.is_emergency', true);

        $this->assertDatabaseHas('blood_requests', [
            'hospital_id' => $hospital->id,
            'is_emergency'=> 1,
        ]);
    }

    public function test_store_with_high_urgency_auto_sets_is_emergency(): void
    {
        Queue::fake();

        [$user] = $this->approvedHospitalUser('autoemer');

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/hospital/requests', $this->validPayload([
            'urgency_level' => 'high',
            'is_emergency'  => false,
        ]));

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_emergency', true)
            ->assertJsonPath('operational_mode.is_emergency', true);
    }

    public function test_store_persists_component_field(): void
    {
        Queue::fake();

        [$user, $hospital] = $this->approvedHospitalUser('comphosp');

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/hospital/requests', $this->validPayload([
            'component' => 'PRBC',
        ]));

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.component', 'PRBC');

        $this->assertDatabaseHas('blood_requests', [
            'hospital_id' => $hospital->id,
            'component'   => 'PRBC',
        ]);
    }

    public function test_store_persists_contact_override_and_province(): void
    {
        Queue::fake();

        [$user, $hospital] = $this->approvedHospitalUser('contacthosp');

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/hospital/requests', $this->validPayload([
            'contact_person' => 'Dr. Override',
            'contact_number' => '09171234567',
            'province'       => 'Metro Manila',
        ]));

        $response->assertCreated()->assertJsonPath('success', true);

        $this->assertDatabaseHas('blood_requests', [
            'hospital_id'    => $hospital->id,
            'contact_person' => 'Dr. Override',
            'contact_number' => '09171234567',
            'province'       => 'Metro Manila',
        ]);
    }

    public function test_store_persists_expiry_time(): void
    {
        Queue::fake();

        [$user] = $this->approvedHospitalUser('expiryhosp');

        Sanctum::actingAs($user);

        $requiredOn  = now()->addDays(2)->toDateString();
        $expiryTime  = now()->addDays(5)->toDateString();

        $response = $this->postJson('/api/hospital/requests', $this->validPayload([
            'required_on' => $requiredOn,
            'expiry_time' => $expiryTime,
        ]));

        $response->assertCreated()->assertJsonPath('success', true);

        $this->assertNotNull($response->json('data.expiry_time'));
    }

    public function test_store_rejects_expiry_time_before_required_on(): void
    {
        [$user] = $this->approvedHospitalUser('badexpiry');

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/hospital/requests', $this->validPayload([
            'required_on' => now()->addDays(5)->toDateString(),
            'expiry_time' => now()->addDays(2)->toDateString(),
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['expiry_time']);
    }

    public function test_store_auto_generates_case_id(): void
    {
        Queue::fake();

        [$user] = $this->approvedHospitalUser('caseidhosp');

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/hospital/requests', $this->validPayload());

        $response->assertCreated();
        $caseId = $response->json('data.case_id');
        $this->assertNotNull($caseId);
        $this->assertMatchesRegularExpression('/^BR-\d{8}-[A-Z0-9]{5}$/', $caseId);
    }

    public function test_store_normalises_legacy_units_needed_key(): void
    {
        Queue::fake();

        [$user, $hospital] = $this->approvedHospitalUser('legacyunits');

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/hospital/requests', [
            'blood_type'        => 'B-',
            'units_needed'      => 4,           // legacy key
            'urgency'           => 'high',       // legacy key
            'location'          => 'Davao',      // legacy key
            'distance_radius_km'=> 40,           // legacy key
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('blood_requests', [
            'hospital_id'    => $hospital->id,
            'units_required' => 4,
            'urgency_level'  => 'high',
            'city'           => 'Davao',
        ]);
    }

    public function test_store_returns_403_when_hospital_not_approved(): void
    {
        $user = User::factory()->create(['role' => 'hospital', 'email' => 'unapproved@example.com']);
        Hospital::create([
            'user_id'       => $user->id,
            'hospital_name' => 'Pending Hospital',
            'location'      => 'Manila',
            'contact_person'=> 'Dr. Pending',
            'contact_number'=> '09170000001',
            'email'         => $user->email,
            'password'      => 'Password123!',
            'status'        => 'pending',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/hospital/requests', $this->validPayload());

        $response->assertForbidden()->assertJsonPath('success', false);
    }

    public function test_store_validates_invalid_urgency_level(): void
    {
        [$user] = $this->approvedHospitalUser('badurgency');

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/hospital/requests', $this->validPayload([
            'urgency_level' => 'extreme', // not in allowed list
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['urgency_level']);
    }

    public function test_store_validates_invalid_component(): void
    {
        [$user] = $this->approvedHospitalUser('badcomp');

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/hospital/requests', $this->validPayload([
            'component' => 'RedCells', // not in allowed list
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['component']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tracking counters stored on create
    // ─────────────────────────────────────────────────────────────────────────

    public function test_store_initialises_tracking_counters_to_zero(): void
    {
        Queue::fake();

        [$user, $hospital] = $this->approvedHospitalUser('trackinit');

        Sanctum::actingAs($user);

        $this->postJson('/api/hospital/requests', $this->validPayload())->assertCreated();

        $bloodRequest = BloodRequest::query()
            ->where('hospital_id', $hospital->id)
            ->latest()
            ->firstOrFail();

        $this->assertEquals(0, $bloodRequest->matched_donors_count);
        $this->assertEquals(0, $bloodRequest->notifications_sent);
        $this->assertEquals(0, $bloodRequest->responses_received);
        $this->assertEquals(0, $bloodRequest->accepted_donors);
        $this->assertEquals(0, $bloodRequest->fulfilled_units);
    }

    public function test_update_rejects_invalid_pending_to_fulfilled_transition(): void
    {
        [$user, $hospital] = $this->approvedHospitalUser('invalidjump');

        $bloodRequest = BloodRequest::create([
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
            'blood_type' => 'A+',
            'units_required' => 2,
            'quantity' => 2,
            'requested_units' => 2,
            'urgency_level' => 'high',
            'city' => 'Manila',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/hospital/requests/{$bloodRequest->id}", [
            'status' => 'fulfilled',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Cannot transition request from pending to fulfilled.');

        $this->assertDatabaseHas('blood_requests', [
            'id' => $bloodRequest->id,
            'status' => 'pending',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Legacy alias routes
    // ─────────────────────────────────────────────────────────────────────────

    public function test_legacy_show_alias_returns_same_response(): void
    {
        [$user, $hospital] = $this->approvedHospitalUser('legacyshow');

        $bloodRequest = BloodRequest::create([
            'hospital_id'    => $hospital->id,
            'hospital_name'  => $hospital->hospital_name,
            'blood_type'     => 'A-',
            'units_required' => 1,
            'quantity'       => 1,
            'requested_units'=> 1,
            'urgency_level'  => 'low',
            'city'           => 'Manila',
            'status'         => 'pending',
        ]);

        Sanctum::actingAs($user);

        $canonical = $this->getJson("/api/hospital/requests/{$bloodRequest->id}");
        $legacy    = $this->getJson("/api/hospital/request/{$bloodRequest->id}");

        $canonical->assertOk();
        $legacy->assertOk();
        $this->assertEquals($canonical->json('data.id'), $legacy->json('data.id'));
        $this->assertEquals($canonical->json('success'), $legacy->json('success'));
    }

    public function test_legacy_list_alias_returns_paginated_envelope(): void
    {
        [$user] = $this->approvedHospitalUser('legacylist');

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/hospital/request/list');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data' => ['data', 'current_page']]);
    }
}
