<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\ChapterApiKey;
use App\Models\ChapterInventory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChapterApiKeyTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create([
            'role' => 'admin',
        ]);
    }

    public function test_admin_can_generate_api_key_for_a_chapter(): void
    {
        $admin = $this->adminUser();
        $chapter = Chapter::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/admin/chapters/{$chapter->id}/api-keys", [
            'label' => 'Test Key',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'key' => ['id', 'label'],
                    'plain_text_key',
                ],
            ]);

        $this->assertDatabaseHas('chapter_api_keys', [
            'chapter_id' => $chapter->id,
            'label' => 'Test Key',
            'is_active' => true,
        ]);
    }

    public function test_generated_key_is_shown_only_once(): void
    {
        $admin = $this->adminUser();
        $chapter = Chapter::factory()->create();

        Sanctum::actingAs($admin);

        $generateResponse = $this->postJson("/api/admin/chapters/{$chapter->id}/api-keys", [
            'label' => 'One Time Key',
        ]);

        $generateResponse->assertCreated();
        $plainTextKey = $generateResponse->json('data.plain_text_key');
        $this->assertNotEmpty($plainTextKey);

        $listResponse = $this->getJson("/api/admin/chapters/{$chapter->id}/api-keys");
        $listResponse->assertOk();

        $first = $listResponse->json('data.0');
        $this->assertArrayNotHasKey('plain_text_key', $first);
        $this->assertArrayHasKey('key_masked', $first);
    }

    public function test_admin_can_revoke_an_api_key(): void
    {
        $admin = $this->adminUser();
        $chapter = Chapter::factory()->create();

        $key = ChapterApiKey::factory()->create([
            'chapter_id' => $chapter->id,
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/admin/chapters/{$chapter->id}/api-keys/{$key->id}");

        $response->assertOk();
        $this->assertDatabaseHas('chapter_api_keys', [
            'id' => $key->id,
            'is_active' => false,
        ]);
    }

    public function test_chapter_can_sync_inventory_with_valid_api_key(): void
    {
        $chapter = Chapter::factory()->create();

        $key = ChapterApiKey::factory()->create([
            'chapter_id' => $chapter->id,
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'X-API-KEY' => $key->api_key,
        ])->postJson('/api/chapters/inventory/sync', [
            'blood_type' => 'O+',
            'component_type' => 'Whole Blood',
            'units_available' => 15,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('chapter_inventories', [
            'chapter_id' => $chapter->id,
            'blood_type' => 'O+',
            'component_type' => 'Whole Blood',
            'units_available' => 15,
            'status' => 'adequate',
        ]);
    }

    public function test_chapter_sync_is_rejected_with_invalid_api_key(): void
    {
        $response = $this->withHeaders([
            'X-API-KEY' => 'invalid-key',
        ])->postJson('/api/chapters/inventory/sync', [
            'blood_type' => 'O+',
            'component_type' => 'Whole Blood',
            'units_available' => 15,
        ]);

        $response->assertUnauthorized();
    }

    public function test_chapter_sync_is_rejected_with_revoked_api_key(): void
    {
        $chapter = Chapter::factory()->create();

        $key = ChapterApiKey::factory()->create([
            'chapter_id' => $chapter->id,
            'is_active' => false,
        ]);

        $response = $this->withHeaders([
            'X-API-KEY' => $key->api_key,
        ])->postJson('/api/chapters/inventory/sync', [
            'blood_type' => 'A+',
            'component_type' => 'Whole Blood',
            'units_available' => 12,
        ]);

        $response->assertUnauthorized();
    }

    public function test_last_used_at_is_updated_on_successful_sync(): void
    {
        $chapter = Chapter::factory()->create();

        $key = ChapterApiKey::factory()->create([
            'chapter_id' => $chapter->id,
            'is_active' => true,
            'last_used_at' => null,
        ]);

        $this->withHeaders([
            'X-API-KEY' => $key->api_key,
        ])->postJson('/api/chapters/inventory/sync', [
            'blood_type' => 'B+',
            'component_type' => 'Whole Blood',
            'units_available' => 9,
        ])->assertOk();

        $key->refresh();
        $this->assertNotNull($key->last_used_at);
    }

    public function test_syncing_updates_existing_inventory_record_not_duplicate(): void
    {
        $chapter = Chapter::factory()->create();

        $key = ChapterApiKey::factory()->create([
            'chapter_id' => $chapter->id,
            'is_active' => true,
        ]);

        ChapterInventory::factory()->create([
            'chapter_id' => $chapter->id,
            'blood_type' => 'AB-',
            'component_type' => 'Whole Blood',
            'units_available' => 2,
            'status' => 'low',
        ]);

        $this->withHeaders([
            'X-API-KEY' => $key->api_key,
        ])->postJson('/api/chapters/inventory/sync', [
            'blood_type' => 'AB-',
            'component_type' => 'Whole Blood',
            'units_available' => 11,
        ])->assertOk();

        $this->assertDatabaseCount('chapter_inventories', 1);
        $this->assertDatabaseHas('chapter_inventories', [
            'chapter_id' => $chapter->id,
            'blood_type' => 'AB-',
            'component_type' => 'Whole Blood',
            'units_available' => 11,
            'status' => 'adequate',
        ]);
    }
}
