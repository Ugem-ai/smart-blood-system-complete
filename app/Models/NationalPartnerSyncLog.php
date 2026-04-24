<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NationalPartnerSyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'actor_user_id',
        'partner_key',
        'status',
        'http_status',
        'request_payload',
        'response_payload',
        'error_message',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'synced_at' => 'datetime',
            'http_status' => 'integer',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
