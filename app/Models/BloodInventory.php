<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BloodInventory extends Model
{
    use HasFactory;

    protected $table = 'blood_inventory';

    protected $fillable = [
        'hospital_id',
        'blood_type',
        'units_available',
        'last_updated',
    ];

    protected function casts(): array
    {
        return [
            'units_available' => 'integer',
            'last_updated' => 'datetime',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
