<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'chapter_id',
        'sync_action',
        'blood_type',
        'component_type',
        'units_changed',
        'sync_status',
        'previous_state',
        'new_state',
        'triggered_by_request_id',
        'triggered_by_user_id',
        'error_message',
        'affected_chapters_count',
        'notes',
        'synced_at',
    ];

    protected $casts = [
        'units_changed' => 'integer',
        'previous_state' => 'json',
        'new_state' => 'json',
        'affected_chapters_count' => 'integer',
        'synced_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the chapter that triggered this sync.
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /**
     * Get the blood request that triggered this sync.
     */
    public function bloodRequest(): BelongsTo
    {
        return $this->belongsTo(BloodRequest::class, 'triggered_by_request_id');
    }

    /**
     * Get the user who triggered this sync.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    /**
     * Check if sync was successful.
     */
    public function wasSuccessful(): bool
    {
        return $this->sync_status === 'completed';
    }

    /**
     * Check if sync failed.
     */
    public function failed(): bool
    {
        return $this->sync_status === 'failed';
    }

    /**
     * Get display name for sync action.
     */
    public function getActionDisplayName(): string
    {
        $names = [
            'create' => 'Created',
            'update' => 'Updated',
            'reserve' => 'Reserved',
            'release' => 'Released',
            'transfer' => 'Transferred',
            'expire' => 'Expired',
            'quarantine' => 'Quarantined',
            'destroy' => 'Destroyed',
        ];

        return $names[$this->sync_action] ?? $this->sync_action;
    }
}
