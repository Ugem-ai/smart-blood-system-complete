<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\ChapterInventory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChapterInventoryTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create([
            'role' => 'admin',
        ]);
    }

    public function test_admin_can_get_all_chapters(): void
    {
        $admin = $this->adminUser();
        Chapter::factory()->count(3)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/chapters');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'chapters',
                    'kpis',
                ],
            ]);

        $this->assertCount(3, $response->json('data.chapters'));
    }

    public function test_admin_can_get_inventory_for_a_specific_chapter(): void
    {
        $admin = $this->adminUser();
        $chapter = Chapter::factory()->create();

        ChapterInventory::factory()->count(2)->create([
            'chapter_id' => $chapter->id,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/admin/chapters/{$chapter->id}/inventory");

        $response->assertOk()
            ->assertJsonPath('data.chapter_id', $chapter->id)
            ->assertJsonStructure([
                'data' => [
                    'chapter_id',
                    'inventory',
                ],
            ]);

        $this->assertCount(2, $response->json('data.inventory'));
    }

    public function test_admin_can_update_inventory_for_a_chapter(): void
    {
        $admin = $this->adminUser();
        $chapter = Chapter::factory()->create();

        $inventory = ChapterInventory::factory()->create([
            'chapter_id' => $chapter->id,
            'blood_type' => 'O+',
            'component_type' => 'Whole Blood',
            'units_available' => 10,
            'status' => 'adequate',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/admin/chapters/{$chapter->id}/inventory/{$inventory->id}", [
            'units_available' => 3,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.units_available', 3)
            ->assertJsonPath('data.status', 'low');

        $this->assertDatabaseHas('chapter_inventories', [
            'id' => $inventory->id,
            'units_available' => 3,
            'status' => 'low',
        ]);
    }

    public function test_admin_can_submit_a_transfer_request(): void
    {
        $admin = $this->adminUser();
        $source = Chapter::factory()->create();
        $destination = Chapter::factory()->create();

        ChapterInventory::factory()->create([
            'chapter_id' => $source->id,
            'blood_type' => 'O+',
            'component_type' => 'Whole Blood',
            'units_available' => 12,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/transfers', [
            'source_chapter_id' => $source->id,
            'destination_chapter_id' => $destination->id,
            'blood_type' => 'O+',
            'component_type' => 'Whole Blood',
            'units_requested' => 2,
            'priority' => 'urgent',
            'reason' => 'Critical shortage',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('chapter_transfer_requests', [
            'source_chapter_id' => $source->id,
            'destination_chapter_id' => $destination->id,
            'blood_type' => 'O+',
            'units_requested' => 2,
            'status' => 'pending',
        ]);
    }

    public function test_admin_can_get_transfer_recommendations(): void
    {
        $admin = $this->adminUser();
        $destination = Chapter::factory()->create([
            'latitude' => 14.2117,
            'longitude' => 121.1653,
        ]);
        $source = Chapter::factory()->create([
            'latitude' => 14.5995,
            'longitude' => 120.9842,
        ]);

        ChapterInventory::factory()->create([
            'chapter_id' => $source->id,
            'blood_type' => 'O+',
            'component_type' => 'Whole Blood',
            'units_available' => 20,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/admin/inventory/recommendations?blood_type=O%2B&units=2&destination_chapter_id={$destination->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'destination_chapter_id',
                    'recommended_sources',
                ],
            ]);

        $this->assertNotEmpty($response->json('data.recommended_sources'));
    }

    public function test_admin_can_get_nearby_chapters(): void
    {
        $admin = $this->adminUser();
        $origin = Chapter::factory()->create([
            'latitude' => 14.5995,
            'longitude' => 120.9842,
        ]);

        $nearby = Chapter::factory()->create([
            'latitude' => 14.5547,
            'longitude' => 121.0244,
        ]);

        ChapterInventory::factory()->create([
            'chapter_id' => $nearby->id,
            'blood_type' => 'A+',
            'units_available' => 8,
            'component_type' => 'Whole Blood',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/admin/chapters/nearby?chapter_id={$origin->id}&radius=100");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'origin_chapter_id',
                    'radius',
                    'nearby_chapters',
                ],
            ]);

        $this->assertNotEmpty($response->json('data.nearby_chapters'));
    }

    public function test_unauthenticated_user_cannot_access_admin_chapter_routes(): void
    {
        $chapter = Chapter::factory()->create();

        $this->getJson('/api/admin/chapters')->assertUnauthorized();
        $this->getJson("/api/admin/chapters/{$chapter->id}/inventory")->assertUnauthorized();
    }
}
