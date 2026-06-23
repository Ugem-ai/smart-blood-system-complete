<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChapterTransferRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_chapter_id',
        'destination_chapter_id',
        'blood_type',
        'component_type',
        'units_requested',
        'priority',
        'reason',
        'status',
    ];

    protected $casts = [
        'source_chapter_id' => 'integer',
        'destination_chapter_id' => 'integer',
        'units_requested' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function sourceChapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class, 'source_chapter_id');
    }

    public function destinationChapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class, 'destination_chapter_id');
    }
}
