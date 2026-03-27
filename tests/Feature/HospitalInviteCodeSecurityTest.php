<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HospitalInviteCodeSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_issue_invite_code_and_code_is_single_use(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $createInvite = $this->postJson('/api/admin/hospital-invites', [
            'expires_in_hours' => 24,
        ]);

        $createInvite->assertCreated();
        $code = (string) $createInvite->json('data.code');
        $this->assertNotSame('', $code);

        $firstRegistration = $this->postJson('/api/v1/register', [
            'name' => 'Dr Invite One',
            'email' => 'invite-one@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'hospital',
            'contact_number' => '09010000001',
            'hospital_name' => 'Invite One Hospital',
            'address' => 'Address One',
            'invite_code' => $code,
        ]);

        $firstRegistration->assertCreated();
        $this->assertDatabaseHas('hospitals', [
            'email' => 'invite-one@example.com',
            'status' => 'pending',
        ]);

        $secondRegistration = $this->postJson('/api/v1/register', [
            'name' => 'Dr Invite Two',
            'email' => 'invite-two@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'hospital',
            'contact_number' => '09010000002',
            'hospital_name' => 'Invite Two Hospital',
            'address' => 'Address Two',
            'invite_code' => $code,
        ]);

        $secondRegistration->assertStatus(403);
        $this->assertDatabaseMissing('hospitals', [
            'email' => 'invite-two@example.com',
        ]);
    }

    public function test_revoked_invite_code_cannot_be_used(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $createInvite = $this->postJson('/api/admin/hospital-invites', [
            'expires_in_hours' => 24,
        ]);

        $createInvite->assertCreated();
        $inviteId = (int) $createInvite->json('data.id');
        $code = (string) $createInvite->json('data.code');

        $this->patchJson('/api/admin/hospital-invites/'.$inviteId.'/revoke')
            ->assertOk();

        $registration = $this->postJson('/api/v1/register', [
            'name' => 'Dr Revoked',
            'email' => 'revoked-invite@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'hospital',
            'contact_number' => '09010000003',
            'hospital_name' => 'Revoked Invite Hospital',
            'address' => 'Revoked Address',
            'invite_code' => $code,
        ]);

        $registration->assertStatus(403);
        $this->assertDatabaseMissing('hospitals', [
            'email' => 'revoked-invite@example.com',
        ]);
    }
}
