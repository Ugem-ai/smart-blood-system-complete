<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\ChapterInventory;
use App\Models\ChapterTransferRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChapterTransferTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create([
            'role' => 'admin',
        ]);
    }

    private function transferPayload(int $sourceId, int $destinationId, int $units = 2): array
    {
        return [
            'source_chapter_id' => $sourceId,
            'destination_chapter_id' => $destinationId,
            'blood_type' => 'O+',
            'component_type' => 'Whole Blood',
            'units_requested' => $units,
            'priority' => 'urgent',
            'reason' => 'Critical shortage',
        ];
    }

    public function test_transfer_request_requires_all_fields(): void
    {
        $admin = $this->adminUser();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/transfers', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'source_chapter_id',
                'destination_chapter_id',
                'blood_type',
                'component_type',
                'units_requested',
                'priority',
            ]);
    }

    public function test_transfer_request_cannot_use_same_source_and_destination(): void
    {
        $admin = $this->adminUser();
        $chapter = Chapter::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/transfers', $this->transferPayload($chapter->id, $chapter->id));

        $response->assertUnprocessable();
    }

    public function test_transfer_request_units_cannot_exceed_source_available_units(): void
    {
        $admin = $this->adminUser();
        $source = Chapter::factory()->create();
        $destination = Chapter::factory()->create();

        ChapterInventory::factory()->create([
            'chapter_id' => $source->id,
            'blood_type' => 'O+',
            'component_type' => 'Whole Blood',
            'units_available' => 1,
            'status' => 'low',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/transfers', $this->transferPayload($source->id, $destination->id, 5));

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Requested units exceed available source inventory.');
    }

    public function test_admin_can_approve_a_transfer_request(): void
    {
        $admin = $this->adminUser();
        $source = Chapter::factory()->create();
        $destination = Chapter::factory()->create();

        $transfer = ChapterTransferRequest::factory()->create([
            'source_chapter_id' => $source->id,
            'destination_chapter_id' => $destination->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/admin/transfers/{$transfer->id}/status", [
            'status' => 'approved',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_admin_can_reject_a_transfer_request(): void
    {
        $admin = $this->adminUser();
        $source = Chapter::factory()->create();
        $destination = Chapter::factory()->create();

        $transfer = ChapterTransferRequest::factory()->create([
            'source_chapter_id' => $source->id,
            'destination_chapter_id' => $destination->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/admin/transfers/{$transfer->id}/status", [
            'status' => 'rejected',
            'rejection_reason' => 'Insufficient logistics support',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'rejected');
    }

    public function test_completing_a_transfer_updates_source_and_destination_inventory(): void
    {
        $admin = $this->adminUser();
        $source = Chapter::factory()->create();
        $destination = Chapter::factory()->create();

        ChapterInventory::factory()->create([
            'chapter_id' => $source->id,
            'blood_type' => 'O+',
            'component_type' => 'Whole Blood',
            'units_available' => 10,
            'status' => 'adequate',
        ]);

        ChapterInventory::factory()->create([
            'chapter_id' => $destination->id,
            'blood_type' => 'O+',
            'component_type' => 'Whole Blood',
            'units_available' => 1,
            'status' => 'low',
        ]);

        $transfer = ChapterTransferRequest::factory()->create([
            'source_chapter_id' => $source->id,
            'destination_chapter_id' => $destination->id,
            'blood_type' => 'O+',
            'component_type' => 'Whole Blood',
            'units_requested' => 4,
            'status' => 'approved',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/admin/transfers/{$transfer->id}/status", [
            'status' => 'completed',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('chapter_inventories', [
            'chapter_id' => $source->id,
            'blood_type' => 'O+',
            'component_type' => 'Whole Blood',
            'units_available' => 6,
        ]);

        $this->assertDatabaseHas('chapter_inventories', [
            'chapter_id' => $destination->id,
            'blood_type' => 'O+',
            'component_type' => 'Whole Blood',
            'units_available' => 5,
        ]);
    }
}
