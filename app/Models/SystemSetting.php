<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'urgency_threshold',
        'notification_rule',
        'past_match_weights',
        'past_match_weight_profiles',
        'control_center',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'past_match_weights' => 'array',
            'past_match_weight_profiles' => 'array',
            'control_center' => 'array',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}