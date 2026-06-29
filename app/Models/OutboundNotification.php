<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutboundNotification extends Model
{
    use HasFactory;

    protected $table = 'outbound_notifications';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'delivered_at' => 'datetime',
    ];
}
