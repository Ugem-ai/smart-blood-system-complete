<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\BloodInventory;
use App\Models\Hospital;
use Illuminate\Support\Collection;

class InventoryMonitoringService
{
    /**
     * @return array<string, int>
     */
    public function thresholds(): array
    {
        return [
            'O-' => 3,
            'O+' => 5,
            'A-' => 4,
            'A+' => 5,
            'B-' => 4,
            'B+' => 5,
            'AB-' => 3,
            'AB+' => 4,
        ];
    }

    public function thresholdFor(string $bloodType): int
    {
        $bloodType = strtoupper(trim($bloodType));

        return $this->thresholds()[$bloodType] ?? 5;
    }

    /**
     * @param array<int, array{blood_type: string, units_available: int}> $inventories
     * @return array{inventory: Collection<int, BloodInventory>, alerts: array<int, array<string, mixed>>}
     */
    public function updateHospitalInventory(Hospital $hospital, array $inventories, ?int $actorUserId = null): array
    {
        $updated = collect();
        $alerts = [];

        foreach ($inventories as $row) {
            $bloodType = strtoupper(trim((string) $row['blood_type']));
            $unitsAvailable = max(0, (int) $row['units_available']);

            $inventory = BloodInventory::query()->updateOrCreate(
                [
                    'hospital_id' => $hospital->id,
                    'blood_type' => $bloodType,
                ],
                [
                    'units_available' => $unitsAvailable,
                    'last_updated' => now(),
                ]
            );

            $updated->push($inventory->fresh());

            if ($this->isLowStock($bloodType, $unitsAvailable)) {
                $alert = [
                    'hospital_id' => $hospital->id,
                    'hospital_name' => $hospital->hospital_name,
                    'blood_type' => $bloodType,
                    'units_available' => $unitsAvailable,
                    'threshold' => $this->thresholdFor($bloodType),
                    'status' => $unitsAvailable === 0 ? 'critical' : 'warning',
                    'message' => sprintf('%s inventory below threshold (%d/%d units)', $bloodType, $unitsAvailable, $this->thresholdFor($bloodType)),
                    'last_updated' => optional($inventory->last_updated)->toDateTimeString(),
                ];

                $alerts[] = $alert;

                ActivityLog::record($actorUserId, 'hospital.inventory-low', [
                    'hospital_id' => $hospital->id,
                    'blood_type' => $bloodType,
                    'units_available' => $unitsAvailable,
                    'threshold' => $this->thresholdFor($bloodType),
                ]);
            }
        }

        ActivityLog::record($actorUserId, 'hospital.inventory-updated', [
            'hospital_id' => $hospital->id,
            'inventory_rows_updated' => $updated->count(),
            'low_stock_alerts' => count($alerts),
        ]);

        return [
            'inventory' => $updated,
            'alerts' => $alerts,
        ];
    }

    public function isLowStock(string $bloodType, int $unitsAvailable): bool
    {
        return $unitsAvailable < $this->thresholdFor($bloodType);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function lowStockAlerts(?Hospital $hospital = null): array
    {
        $query = BloodInventory::query()->with('hospital')->orderBy('units_available')->orderByDesc('last_updated');

        if ($hospital) {
            $query->where('hospital_id', $hospital->id);
        }

        return $query->get()
            ->filter(fn (BloodInventory $inventory) => $this->isLowStock($inventory->blood_type, (int) $inventory->units_available))
            ->map(function (BloodInventory $inventory) {
                $threshold = $this->thresholdFor($inventory->blood_type);

                return [
                    'hospital_id' => $inventory->hospital_id,
                    'hospital_name' => $inventory->hospital?->hospital_name,
                    'blood_type' => $inventory->blood_type,
                    'units_available' => (int) $inventory->units_available,
                    'threshold' => $threshold,
                    'status' => (int) $inventory->units_available === 0 ? 'critical' : 'warning',
                    'message' => sprintf('%s inventory below threshold (%d/%d units)', $inventory->blood_type, (int) $inventory->units_available, $threshold),
                    'last_updated' => optional($inventory->last_updated)->toDateTimeString(),
                ];
            })
            ->values()
            ->all();
    }
}
