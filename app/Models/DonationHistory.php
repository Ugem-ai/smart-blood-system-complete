<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonationHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'donor_id',
        'hospital_id',
        'request_id',
        'donated_at',
        'donation_date',
        'location',
        'units',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'donated_at' => 'datetime',
            'donation_date' => 'date',
        ];
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(BloodRequest::class, 'request_id');
    }
}
