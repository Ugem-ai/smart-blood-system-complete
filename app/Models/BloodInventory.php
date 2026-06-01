<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BloodInventory extends Model
{
    use HasFactory;

    protected $table = 'blood_inventory';

    protected $fillable = [
        'chapter_id',
        'hospital_id',
        'blood_type',
        'component_type',
        'units_available',
        'units_reserved',
        'units_in_transit',
        'expiration_date',
        'inventory_status',
        'reserved_for_request_id',
        'critical_level',
        'reorder_level',
        'notes',
        'last_updated_at',
        'last_updated',
    ];

    protected $casts = [
        'chapter_id' => 'integer',
        'hospital_id' => 'integer',
        'units_available' => 'integer',
        'units_reserved' => 'integer',
        'units_in_transit' => 'integer',
        'expiration_date' => 'date',
        'critical_level' => 'integer',
        'reorder_level' => 'integer',
        'last_updated_at' => 'datetime',
        'last_updated' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the chapter that owns this inventory (PRC branch).
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /**
     * Get the hospital (for backward compatibility).
     */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    /**
     * Get the blood request this inventory is reserved for.
     */
    public function reservedForRequest(): BelongsTo
    {
        return $this->belongsTo(BloodRequest::class, 'reserved_for_request_id');
    }

    /**
     * Get all transactions for this inventory.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    /**
     * Get total effective units (available - reserved - in_transit).
     */
    public function getEffectiveUnitsAttribute(): int
    {
        return max(0, ($this->units_available ?? 0) - ($this->units_reserved ?? 0) - ($this->units_in_transit ?? 0));
    }

    /**
     * Check if inventory is at critical level.
     */
    public function isCritical(): bool
    {
        return ($this->units_available ?? 0) <= ($this->critical_level ?? 2)
            && ($this->inventory_status ?? 'available') === 'available';
    }

    /**
     * Check if reorder is needed.
     */
    public function needsReorder(): bool
    {
        return ($this->units_available ?? 0) <= ($this->reorder_level ?? 5)
            && ($this->inventory_status ?? 'available') === 'available';
    }

    /**
     * Check if expired.
     */
    public function isExpired(): bool
    {
        return $this->expiration_date
            && $this->expiration_date->isPast()
            && ($this->inventory_status ?? 'available') !== 'expired';
    }

    /**
     * Reserve units for a blood request.
     */
    public function reserve(int $units, BloodRequest $request): bool
    {
        $available = $this->units_available ?? 0;
        if ($available < $units) {
            return false;
        }

        $this->units_available = $available - $units;
        $this->units_reserved = ($this->units_reserved ?? 0) + $units;
        $this->reserved_for_request_id = $request->id;
        $this->last_updated_at = now();
        $this->save();

        // Log the transaction
        if (class_exists(InventoryTransaction::class)) {
            InventoryTransaction::create([
                'blood_inventory_id' => $this->id,
                'chapter_id' => $this->chapter_id,
                'transaction_type' => 'reserve',
                'quantity_changed' => -$units,
                'quantity_before' => $available,
                'quantity_after' => $this->units_available,
                'blood_request_id' => $request->id,
                'performed_by_user_id' => auth()->id() ?? 1,
                'reason' => "Reserved for blood request #{$request->id}",
            ]);
        }

        return true;
    }

    /**
     * Release reservation.
     */
    public function releaseReservation(int $units): bool
    {
        $reserved = $this->units_reserved ?? 0;
        if ($reserved < $units) {
            return false;
        }

        $this->units_reserved = $reserved - $units;
        $this->units_available = ($this->units_available ?? 0) + $units;

        if ($this->units_reserved === 0) {
            $this->reserved_for_request_id = null;
        }

        $this->last_updated_at = now();
        $this->save();

        // Log the transaction
        if (class_exists(InventoryTransaction::class)) {
            InventoryTransaction::create([
                'blood_inventory_id' => $this->id,
                'chapter_id' => $this->chapter_id,
                'transaction_type' => 'release_reservation',
                'quantity_changed' => $units,
                'quantity_before' => $this->units_available - $units,
                'quantity_after' => $this->units_available,
                'performed_by_user_id' => auth()->id() ?? 1,
                'reason' => 'Reservation released',
            ]);
        }

        return true;
    }

    /**
     * Record usage.
     */
    public function recordUsage(int $units, string $reason = 'Blood usage recorded'): bool
    {
        $available = $this->units_available ?? 0;
        if ($available < $units) {
            return false;
        }

        $this->units_available = $available - $units;
        $this->last_updated_at = now();
        $this->save();

        // Log the transaction
        if (class_exists(InventoryTransaction::class)) {
            InventoryTransaction::create([
                'blood_inventory_id' => $this->id,
                'chapter_id' => $this->chapter_id,
                'transaction_type' => 'usage',
                'quantity_changed' => -$units,
                'quantity_before' => $available,
                'quantity_after' => $this->units_available,
                'performed_by_user_id' => auth()->id() ?? 1,
                'reason' => $reason,
            ]);
        }

        return true;
    }

    /**
     * Mark as expired.
     */
    public function markExpired(): bool
    {
        $this->inventory_status = 'expired';
        $this->last_updated_at = now();
        $this->save();

        if (class_exists(InventoryTransaction::class)) {
            InventoryTransaction::create([
                'blood_inventory_id' => $this->id,
                'chapter_id' => $this->chapter_id,
                'transaction_type' => 'expiration',
                'quantity_changed' => 0,
                'quantity_before' => $this->units_available,
                'quantity_after' => 0,
                'performed_by_user_id' => auth()->id() ?? 1,
                'reason' => $this->expiration_date ? "Blood expired on {$this->expiration_date->format('Y-m-d')}" : 'Blood expired',
            ]);
        }

        return true;
    }
}
