<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Donor extends Model
{
    use HasFactory;

    protected $hidden = [
        'contact_number',
        'phone',
        'email',
        'password',
    ];

    protected $fillable = [
        'user_id',
        'name',
        'blood_type',
        'city',
        'contact_number',
        'phone',
        'latitude',
        'longitude',
        'email',
        'password',
        'last_donation_date',
        'availability',
        'reliability_score',
        'privacy_consent_at',
        'donor_preferences',
    ];

    protected function casts(): array
    {
        return [
            'last_donation_date' => 'date',
            'availability' => 'boolean',
            'contact_number' => 'encrypted',
            'phone' => 'encrypted',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'reliability_score' => 'decimal:2',
            'privacy_consent_at' => 'datetime',
            'donor_preferences' => 'array',
            'password' => 'hashed',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function donationHistories(): HasMany
    {
        return $this->hasMany(DonationHistory::class);
    }

    public function requestResponses(): HasMany
    {
        return $this->hasMany(DonorRequestResponse::class);
    }

    public function requestMatches(): HasMany
    {
        return $this->hasMany(RequestMatch::class);
    }

    public function isEligibleForDonation(int $minimumIntervalDays = 56): bool
    {
        if (! $this->last_donation_date) {
            return true;
        }

        return Carbon::parse($this->last_donation_date)->diffInDays(now()) >= $minimumIntervalDays;
    }

    public function daysSinceLastDonation(): ?int
    {
        if (! $this->last_donation_date) {
            return null;
        }

        return Carbon::parse($this->last_donation_date)->diffInDays(now());
    }

    public function nextEligibleDonationDate(int $minimumIntervalDays = 56): ?CarbonInterface
    {
        if (! $this->last_donation_date) {
            return null;
        }

        return Carbon::parse($this->last_donation_date)->addDays($minimumIntervalDays);
    }

    /**
     * Human-readable reliability tier: Elite / Reliable / Moderate / Unreliable.
     */
    public function reliabilityLabel(): string
    {
        $score = (float) ($this->reliability_score ?? 50);

        return match (true) {
            $score >= 80 => 'Elite',
            $score >= 60 => 'Reliable',
            $score >= 35 => 'Moderate',
            default      => 'Unreliable',
        };
    }

    /**
     * Returns true when the donor is in the top two tiers (score ≥ 60).
     */
    public function isHighlyReliable(): bool
    {
        return ((float) ($this->reliability_score ?? 50)) >= 60;
    }
}
