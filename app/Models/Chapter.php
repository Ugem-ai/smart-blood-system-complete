<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chapter extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'location',
        'is_active',
        'chapter_code',
        'chapter_name',
        'address',
        'latitude',
        'longitude',
        'contact_number',
        'email',
        'region',
        'province',
        'city',
        'status',
        'capacity_units',
        'notes',
        'synced_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'capacity_units' => 'integer',
        'is_active' => 'boolean',
        'synced_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get all blood inventory records for this chapter.
     */
    public function bloodInventory(): HasMany
    {
        return $this->hasMany(BloodInventory::class);
    }

    public function chapterInventories(): HasMany
    {
        return $this->hasMany(ChapterInventory::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ChapterApiKey::class);
    }

    public function transferRequestsOut(): HasMany
    {
        return $this->hasMany(ChapterTransferRequest::class, 'source_chapter_id');
    }

    public function transferRequestsIn(): HasMany
    {
        return $this->hasMany(ChapterTransferRequest::class, 'destination_chapter_id');
    }

    /**
     * Get all transfers from this chapter.
     */
    public function transfersOut(): HasMany
    {
        return $this->hasMany(InventoryTransfer::class, 'source_chapter_id');
    }

    /**
     * Get all transfers to this chapter.
     */
    public function transfersIn(): HasMany
    {
        return $this->hasMany(InventoryTransfer::class, 'destination_chapter_id');
    }

    /**
     * Get all sync logs for this chapter.
     */
    public function syncLogs(): HasMany
    {
        return $this->hasMany(InventorySyncLog::class);
    }

    /**
     * Get all transactions for this chapter.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    /**
     * Get total units available in this chapter for all blood types.
     */
    public function totalUnitsAvailable(): int
    {
        return $this->bloodInventory()
            ->where('inventory_status', 'available')
            ->sum('units_available');
    }

    /**
     * Get critical shortage status.
     */
    public function getCriticalShortages()
    {
        return $this->bloodInventory()
            ->whereRaw('units_available <= critical_level')
            ->where('inventory_status', 'available')
            ->get();
    }

    /**
     * Get nearby chapters within specified distance.
     */
    public static function getNearbyChapters(float $latitude, float $longitude, int $distanceKm = 50): \Illuminate\Database\Eloquent\Collection
    {
        $earthRadiusKm = 6371;
        $minLat = $latitude - ($distanceKm / 111.0);
        $maxLat = $latitude + ($distanceKm / 111.0);
        $minLon = $longitude - ($distanceKm / (111.0 * cos(deg2rad($latitude))));
        $maxLon = $longitude + ($distanceKm / (111.0 * cos(deg2rad($latitude))));

        return self::query()
            ->where('status', 'active')
            ->whereBetween('latitude', [$minLat, $maxLat])
            ->whereBetween('longitude', [$minLon, $maxLon])
            ->orderBy('latitude')
            ->orderBy('longitude')
            ->get();
    }

    /**
     * Calculate distance to another chapter.
     */
    public function distanceTo(Chapter $chapter): float
    {
        if (!$this->latitude || !$this->longitude || !$chapter->latitude || !$chapter->longitude) {
            return PHP_FLOAT_MAX;
        }

        $earthRadiusKm = 6371;
        $dLat = deg2rad($chapter->latitude - $this->latitude);
        $dLon = deg2rad($chapter->longitude - $this->longitude);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($this->latitude)) * cos(deg2rad($chapter->latitude))
            * sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadiusKm * $c, 2);
    }
}
