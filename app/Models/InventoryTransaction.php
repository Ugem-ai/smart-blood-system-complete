<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'blood_inventory_id',
        'chapter_id',
        'transaction_type',
        'quantity_changed',
        'quantity_before',
        'quantity_after',
        'blood_request_id',
        'inventory_transfer_id',
        'donor_id',
        'performed_by_user_id',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'blood_inventory_id' => 'integer',
        'chapter_id' => 'integer',
        'quantity_changed' => 'integer',
        'quantity_before' => 'integer',
        'quantity_after' => 'integer',
        'metadata' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function bloodInventory(): BelongsTo
    {
        return $this->belongsTo(BloodInventory::class);
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    public function bloodRequest(): BelongsTo
    {
        return $this->belongsTo(BloodRequest::class);
    }

    public function inventoryTransfer(): BelongsTo
    {
        return $this->belongsTo(InventoryTransfer::class);
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }
}
