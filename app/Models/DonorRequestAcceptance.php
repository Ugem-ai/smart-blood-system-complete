<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonorRequestAcceptance extends Model
{
    use HasFactory;

    protected $fillable = [
        'donor_id',
        'blood_request_id',
        'distance_km_at_acceptance',
        'status',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'distance_km_at_acceptance' => 'decimal:2',
            'accepted_at' => 'datetime',
        ];
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }

    public function bloodRequest(): BelongsTo
    {
        return $this->belongsTo(BloodRequest::class);
    }

    public static function expirePendingForRequest(BloodRequest $bloodRequest): int
    {
        if ($bloodRequest->expiry_time === null || now()->lte($bloodRequest->expiry_time)) {
            return 0;
        }

        return self::query()
            ->where('blood_request_id', $bloodRequest->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'declined',
                'accepted_at' => null,
            ]);
    }
}
