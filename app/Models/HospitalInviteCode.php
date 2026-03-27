<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HospitalInviteCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code_hash',
        'email',
        'domain',
        'expires_at',
        'used_at',
        'used_by_email',
        'issued_by_user_id',
        'revoked_at',
        'revoked_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
