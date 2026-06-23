<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChapterApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'chapter_id',
        'api_key',
        'label',
        'last_used_at',
        'is_active',
    ];

    protected $casts = [
        'chapter_id' => 'integer',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }
}
