<?php

namespace Tests\Feature;

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HospitalInventoryMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_hospital_can_update_inventory_and_receive_low_stock_alerts(): void
    {
        $hospitalUser = User::factory()->create(['role' => 'hospital']);
        $hospital = $this->makeHospital($hospitalUser);

        Sanctum::actingAs($hospitalUser);

        $response = $this->putJson('/api/hospital/inventory', [
            'inventories' => [
                ['blood_type' => 'O-', 'units_available' => 2],
                ['blood_type' => 'A+', 'units_available' => 8],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.hospital_id', $hospital->id);
        $response->assertJsonCount(2, 'data.inventory');
        $response->assertJsonPath('data.low_stock_alerts.0.blood_type', 'O-');
        $response->assertJsonPath('data.low_stock_alerts.0.threshold', 3);

        $this->assertDatabaseHas('blood_inventory', [
            'hospital_id' => $hospital->id,
            'blood_type' => 'O-',
            'units_available' => 2,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'hospital.inventory-low',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'hospital.inventory-updated',
        ]);
    }

    public function test_hospital_inventory_endpoint_returns_snapshot_and_alerts(): void
    {
        $hospitalUser = User::factory()->create(['role' => 'hospital']);
        $hospital = $this->makeHospital($hospitalUser);

        Sanctum::actingAs($hospitalUser);

        $this->putJson('/api/hospital/inventory', [
            'inventories' => [
                ['blood_type' => 'O-', 'units_available' => 1],
                ['blood_type' => 'B+', 'units_available' => 6],
            ],
        ])->assertOk();

        $response = $this->getJson('/api/hospital/inventory');

        $response->assertOk();
        $response->assertJsonPath('data.hospital_id', $hospital->id);
        $response->assertJsonCount(2, 'data.inventory');
        $response->assertJsonPath('data.low_stock_alerts.0.blood_type', 'O-');
    }

    public function test_admin_dashboard_includes_low_inventory_alerts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $hospitalUser = User::factory()->create(['role' => 'hospital']);
        $hospital = $this->makeHospital($hospitalUser);

        Sanctum::actingAs($hospitalUser);
        $this->putJson('/api/hospital/inventory', [
            'inventories' => [
                ['blood_type' => 'O-', 'units_available' => 0],
                ['blood_type' => 'AB+', 'units_available' => 4],
            ],
        ])->assertOk();

        Sanctum::actingAs($admin);
        $dashboard = $this->getJson('/api/admin/dashboard');

        $dashboard->assertOk();
        $dashboard->assertJsonPath('low_inventory_alerts.0.hospital_id', $hospital->id);
        $dashboard->assertJsonPath('low_inventory_alerts.0.blood_type', 'O-');
        $dashboard->assertJsonPath('low_inventory_alerts.0.status', 'critical');
    }

    private function makeHospital(User $hospitalUser): Hospital
    {
        return Hospital::create([
            'user_id' => $hospitalUser->id,
            'hospital_name' => 'Inventory Hospital',
            'address' => 'Inventory Street',
            'location' => 'Manila',
            'contact_person' => 'Dr Inventory',
            'contact_number' => '09179990001',
            'email' => $hospitalUser->email,
            'password' => 'Password123!',
            'status' => 'approved',
        ]);
    }
}

