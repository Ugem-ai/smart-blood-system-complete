<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hospital extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'hospital_name',
        'address',
        'location',
        'latitude',
        'longitude',
        'contact_person',
        'contact_number',
        'email',
        'password',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'address' => 'encrypted',
            'location' => 'encrypted',
            'contact_person' => 'encrypted',
            'contact_number' => 'encrypted',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'password' => 'hashed',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bloodRequests(): HasMany
    {
        return $this->hasMany(BloodRequest::class);
    }

    public function donationHistories(): HasMany
    {
        return $this->hasMany(DonationHistory::class);
    }

    public function bloodInventories(): HasMany
    {
        return $this->hasMany(BloodInventory::class);
    }
}
