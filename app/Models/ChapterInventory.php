<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChapterInventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'chapter_id',
        'blood_type',
        'component_type',
        'units_available',
        'status',
        'last_synced_at',
    ];

    protected $casts = [
        'chapter_id' => 'integer',
        'units_available' => 'integer',
        'last_synced_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }
}
