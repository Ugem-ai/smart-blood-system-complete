<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_chapter_id',
        'destination_chapter_id',
        'blood_request_id',
        'blood_type',
        'component_type',
        'units_requested',
        'units_approved',
        'units_transferred',
        'expiration_date',
        'transfer_status',
        'priority_level',
        'reason_for_transfer',
        'rejection_reason',
        'approved_at',
        'completed_at',
        'approved_by_user_id',
        'created_by_user_id',
        'notes',
    ];

    protected $casts = [
        'units_requested' => 'integer',
        'units_approved' => 'integer',
        'units_transferred' => 'integer',
        'expiration_date' => 'date',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the source (sending) chapter.
     */
    public function sourceChapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class, 'source_chapter_id');
    }

    /**
     * Get the destination (receiving) chapter.
     */
    public function destinationChapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class, 'destination_chapter_id');
    }

    /**
     * Get the associated blood request.
     */
    public function bloodRequest(): BelongsTo
    {
        return $this->belongsTo(BloodRequest::class);
    }

    /**
     * Get the user who created this transfer request.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get the user who approved this transfer.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * Approve the transfer request.
     */
    public function approve(int $unitsApproved): bool
    {
        if ($unitsApproved > $this->units_requested) {
            return false;
        }

        $this->units_approved = $unitsApproved;
        $this->transfer_status = 'approved';
        $this->approved_at = now();
        $this->approved_by_user_id = auth()->id();
        $this->save();

        return true;
    }

    /**
     * Reject the transfer request.
     */
    public function reject(string $reason): bool
    {
        $this->transfer_status = 'rejected';
        $this->rejection_reason = $reason;
        $this->save();

        return true;
    }

    /**
     * Mark as in transit.
     */
    public function markInTransit(): bool
    {
        $this->transfer_status = 'in_transit';
        $this->save();

        return true;
    }

    /**
     * Complete the transfer.
     */
    public function complete(int $unitsTransferred): bool
    {
        if ($unitsTransferred > ($this->units_approved ?? 0)) {
            return false;
        }

        $this->units_transferred = $unitsTransferred;
        $this->transfer_status = 'completed';
        $this->completed_at = now();
        $this->save();

        return true;
    }

    /**
     * Cancel the transfer.
     */
    public function cancel(string $reason = null): bool
    {
        $this->transfer_status = 'cancelled';
        if ($reason) {
            $this->notes = ($this->notes ? $this->notes . "\n" : '') . "Cancelled: {$reason}";
        }
        $this->save();

        return true;
    }

    /**
     * Check if transfer can be approved.
     */
    public function canBeApproved(): bool
    {
        return in_array($this->transfer_status, ['pending']);
    }

    /**
     * Check if transfer can be rejected.
     */
    public function canBeRejected(): bool
    {
        return in_array($this->transfer_status, ['pending']);
    }

    /**
     * Check if transfer is urgent or emergency.
     */
    public function isUrgent(): bool
    {
        return in_array($this->priority_level, ['urgent', 'emergency']);
    }
}
