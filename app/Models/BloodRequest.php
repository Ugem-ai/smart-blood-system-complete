<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BloodRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_id',
        'hospital_id',
        'hospital_name',
        'contact_person',
        'contact_number',
        'blood_type',
        'component',
        'reason',
        'units_required',
        'quantity',
        'requested_units',
        'urgency_level',
        'city',
        'province',
        'latitude',
        'longitude',
        'distance_limit_km',
        'required_on',
        'expiry_time',
        'status',
        'is_emergency',
        'matched_donors',
        'matched_donors_count',
        'notifications_sent',
        'responses_received',
        'accepted_donors',
        'fulfilled_units',
        'donor_assignment_confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'required_on'                  => 'date',
            'expiry_time'                  => 'datetime',
            'donor_assignment_confirmed_at' => 'datetime',
            'latitude'                     => 'decimal:7',
            'longitude'                    => 'decimal:7',
            'distance_limit_km'            => 'decimal:2',
            'is_emergency'                 => 'boolean',
            'matched_donors'               => 'array',
            'matched_donors_count'         => 'integer',
            'notifications_sent'           => 'integer',
            'responses_received'           => 'integer',
            'accepted_donors'              => 'integer',
            'fulfilled_units'              => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BloodRequest $request) {
            if (empty($request->case_id)) {
                $request->case_id = sprintf(
                    'BR-%s-%s',
                    now()->format('Ymd'),
                    strtoupper(substr(uniqid('', false), -5))
                );
            }
        });
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function donorResponses(): HasMany
    {
        return $this->hasMany(DonorRequestResponse::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(RequestMatch::class, 'blood_request_id');
    }

    public function donationHistories(): HasMany
    {
        return $this->hasMany(DonationHistory::class, 'request_id');
    }
}
