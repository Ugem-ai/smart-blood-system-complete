<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'actor_user_id',
        'action',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public static function record(?int $actorUserId, string $action, array $details = []): void
    {
        self::create([
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'details' => self::normalizeDetails($action, $details),
        ]);
    }

    public static function normalizeDetails(string $action, array $details = []): array
    {
        $normalized = $details;

        if (! isset($normalized['status']) || ! is_string($normalized['status']) || $normalized['status'] === '') {
            $normalized['status'] = self::inferStatus($action, $normalized);
        }

        if (! isset($normalized['severity']) || ! is_string($normalized['severity']) || $normalized['severity'] === '') {
            $normalized['severity'] = self::inferSeverity($action, $normalized);
        }

        if (! isset($normalized['category']) || ! is_string($normalized['category']) || $normalized['category'] === '') {
            $normalized['category'] = self::inferCategory($action);
        }

        return collect($normalized)
            ->reject(fn ($value) => $value === null)
            ->all();
    }

    public static function inferStatus(string $action, array $details = []): string
    {
        $explicitStatus = $details['status'] ?? $details['outcome'] ?? null;

        if (is_string($explicitStatus) && $explicitStatus !== '') {
            return Str::lower($explicitStatus);
        }

        if (Str::contains($action, ['failed', 'rejected', 'denied'])) {
            return 'failed';
        }

        if (Str::contains($action, ['unauthorized', 'blocked'])) {
            return 'blocked';
        }

        if (Str::contains($action, ['paused', 'throttled', 'alert', 'inventory-low'])) {
            return 'warning';
        }

        return 'success';
    }

    public static function inferSeverity(string $action, array $details = []): string
    {
        $explicitSeverity = $details['severity'] ?? null;

        if (is_string($explicitSeverity) && $explicitSeverity !== '') {
            return Str::lower($explicitSeverity);
        }

        return match (true) {
            Str::contains($action, ['unauthorized', 'security']) => 'critical',
            Str::contains($action, ['failed', 'rejected', 'suspended']) => 'high',
            Str::contains($action, ['paused', 'throttled', 'alert', 'inventory-low']) => 'medium',
            Str::contains($action, ['viewed', 'access', 'dashboard']) => 'low',
            default => 'info',
        };
    }

    public static function inferCategory(string $action): string
    {
        return match (true) {
            Str::startsWith($action, 'auth.') => 'authentication',
            Str::contains($action, ['unauthorized', 'access']) => 'access',
            Str::contains($action, ['notification', 'device-token']) => 'notifications',
            Str::contains($action, ['matching', 'past-match', 'matched-donors']) => 'matching',
            Str::contains($action, ['blood-request', 'request.']) => 'blood_requests',
            Str::contains($action, ['hospital.approved', 'hospital.rejected', 'donor.suspended', 'donor.prioritized', 'hospital.toggle_status', 'invite_code']) => 'admin',
            Str::contains($action, ['inventory', 'national-integration', 'emergency-broadcast-mode']) => 'system',
            Str::contains($action, ['data.access', 'dashboard', 'profile']) => 'data_access',
            default => 'operations',
        };
    }
}
