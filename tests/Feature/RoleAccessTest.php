<?php

namespace Tests\Feature;

use App\Models\Donor;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_donor_sees_donor_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'donor']);
        Donor::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'blood_type' => 'O+',
            'city' => 'Lagos',
            'contact_number' => '08000000000',
            'email' => $user->email,
            'password' => 'password',
            'availability' => true,
            'privacy_consent_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('donor.dashboard', absolute: false));
        $this->actingAs($user)->get(route('donor.dashboard'))->assertOk();
    }

    public function test_hospital_sees_hospital_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'hospital']);
        Hospital::create([
            'user_id' => $user->id,
            'hospital_name' => 'CarePoint',
            'location' => 'Lagos',
            'contact_person' => 'Dr. Hope',
            'contact_number' => '08011112222',
            'email' => $user->email,
            'password' => 'password',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('hospital.dashboard', absolute: false));
        $this->actingAs($user)->get(route('hospital.dashboard'))->assertOk();
    }

    public function test_admin_sees_admin_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('admin.dashboard', absolute: false));
        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_role_middleware_blocks_wrong_dashboard_access(): void
    {
        $user = User::factory()->create(['role' => 'donor']);

        $this->actingAs($user)->get(route('hospital.dashboard'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_hospital_registration_creates_hospital_user(): void
    {
        $response = $this->post(route('register.hospital.store'), [
            'hospital_name' => 'City Hospital',
            'location' => 'Abuja',
            'contact_person' => 'Hospital User',
            'contact_number' => '08044444444',
            'email' => 'hospital@example.com',
            'hospital_registration_code' => 'test-hospital-code',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('login', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'hospital@example.com',
            'role' => 'hospital',
        ]);

        $this->assertDatabaseHas('hospitals', [
            'email' => 'hospital@example.com',
            'status' => 'pending',
        ]);
    }
}

