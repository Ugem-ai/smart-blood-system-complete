<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SystemSettingsService
{
    private const CACHE_KEY = 'system:settings:v1';

    private const SINGLETON_ID = 1;

    public const DEFAULT_URGENCY_THRESHOLD = 70;

    public const DEFAULT_NOTIFICATION_RULE = 'critical-only';

    public const DEFAULT_PAST_MATCH_WEIGHTS = [
        'priority' => 0.25,
        'availability' => 0.20,
        'distance' => 0.25,
        'time' => 0.30,
    ];

    private const URGENCY_PROFILE_MULTIPLIERS = [
        'low' => [
            'priority' => 0.82,
            'availability' => 1.14,
            'distance' => 1.10,
            'time' => 0.92,
        ],
        'medium' => [
            'priority' => 1.0,
            'availability' => 1.0,
            'distance' => 1.0,
            'time' => 1.0,
        ],
        'high' => [
            'priority' => 1.12,
            'availability' => 0.95,
            'distance' => 0.95,
            'time' => 1.18,
        ],
        'critical' => [
            'priority' => 1.20,
            'availability' => 0.88,
            'distance' => 0.90,
            'time' => 1.35,
        ],
    ];

    private const URGENCY_LEVELS = ['low', 'medium', 'high', 'critical'];

    private const CONTROL_CENTER_MODES = ['normal', 'emergency', 'manual_override'];

    public function current(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached)) {
            return $this->normalize($cached);
        }

        if (! $this->tableAvailable()) {
            return $this->defaults();
        }

        $record = SystemSetting::query()
            ->with('updatedBy:id,name')
            ->find(self::SINGLETON_ID);
        $settings = $record
            ? $this->normalize([
                'urgency_threshold' => $record->urgency_threshold,
                'notification_rule' => $record->notification_rule,
                'past_match_weights' => $record->past_match_weights,
                'past_match_weight_profiles' => $this->storedProfilesAvailable() ? $record->past_match_weight_profiles : null,
                'control_center' => $this->controlCenterAvailable() ? $record->control_center : null,
                'updated_at' => optional($record->updated_at)?->toISOString(),
                'updated_by' => $record->updated_by,
                'updated_by_name' => optional($record->updatedBy)->name,
            ])
            : $this->defaults();

        $this->syncCache($settings);

        return $settings;
    }

    public function pastMatchWeights(?string $urgencyLevel = null): array
    {
        $settings = $this->current();

        if ($urgencyLevel === null) {
            return $settings['past_match_weights'];
        }

        return $settings['past_match_weight_profiles'][$this->normalizeUrgencyLevel($urgencyLevel)]
            ?? $settings['past_match_weights'];
    }

    public function pastMatchWeightProfiles(): array
    {
        return $this->current()['past_match_weight_profiles'];
    }

    public function update(array $attributes, ?int $actorUserId = null): array
    {
        $settings = $this->normalize($attributes);

        if ($this->tableAvailable()) {
            $payload = [
                'urgency_threshold' => $settings['urgency_threshold'],
                'notification_rule' => $settings['notification_rule'],
                'past_match_weights' => $settings['past_match_weights'],
                'updated_by' => $actorUserId,
            ];

            if ($this->storedProfilesAvailable()) {
                $payload['past_match_weight_profiles'] = $settings['past_match_weight_profiles'];
            }

            if ($this->controlCenterAvailable()) {
                $payload['control_center'] = $settings['control_center'];
            }

            $record = SystemSetting::query()->updateOrCreate(
                ['id' => self::SINGLETON_ID],
                $payload
            );

            $record->loadMissing('updatedBy:id,name');

            $settings['updated_at'] = optional($record->updated_at)?->toISOString();
            $settings['updated_by'] = $record->updated_by;
            $settings['updated_by_name'] = optional($record->updatedBy)->name;
        }

        $this->syncCache($settings);

        ActivityLog::record($actorUserId, 'system.settings.updated', [
            'target_type' => 'system_settings',
            'target_id' => self::SINGLETON_ID,
            'target_label' => 'System settings',
            'category' => 'admin',
            'severity' => 'medium',
            'status' => 'success',
            'urgency_threshold' => $settings['urgency_threshold'],
            'notification_rule' => $settings['notification_rule'],
            'past_match_weights' => $settings['past_match_weights'],
            'past_match_weight_profiles' => $settings['past_match_weight_profiles'],
            'control_center' => $settings['control_center'],
        ]);

        return $settings;
    }

    public function defaults(): array
    {
        $baseWeights = self::DEFAULT_PAST_MATCH_WEIGHTS;

        return [
            'urgency_threshold' => self::DEFAULT_URGENCY_THRESHOLD,
            'notification_rule' => self::DEFAULT_NOTIFICATION_RULE,
            'past_match_weights' => $baseWeights,
            'past_match_weight_profiles' => $this->deriveUrgencyProfiles($baseWeights),
            'control_center' => $this->defaultControlCenter($baseWeights),
            'updated_at' => null,
            'updated_by' => null,
            'updated_by_name' => null,
        ];
    }

    public function formatWeightExpression(?string $urgencyLevel = null): string
    {
        $weights = $this->pastMatchWeights($urgencyLevel);

        return sprintf(
            '(%.2f × priority) + (%.2f × availability) + (%.2f × distance) + (%.2f × time)',
            $weights['priority'],
            $weights['availability'],
            $weights['distance'],
            $weights['time']
        );
    }

    private function normalize(array $attributes): array
    {
        $defaults = $this->defaults();
        $controlCenter = $this->normalizeControlCenter(
            $attributes['control_center'] ?? null,
            $defaults['control_center']
        );

        $baseWeights = $this->normalizeWeights(
            $attributes['past_match_weights']
                ?? $attributes['weights']
                ?? ($controlCenter['matching']['mode_weights']['normal'] ?? $defaults['past_match_weights'])
        );

        $profiles = $this->normalizeWeightProfiles(
            $attributes['past_match_weight_profiles'] ?? $attributes['weight_profiles'] ?? null,
            $this->mapModeWeightsToProfiles($controlCenter['matching']['mode_weights'], $baseWeights)
        );
        $baseWeights = $profiles['medium'];

        $urgencyThreshold = max(1, min(100, (int) ($attributes['urgency_threshold'] ?? $controlCenter['emergency']['urgency_threshold'] ?? $defaults['urgency_threshold'])));
        $notificationRule = (string) ($attributes['notification_rule'] ?? $controlCenter['notifications']['rule'] ?? $defaults['notification_rule']);
        $controlCenter = $this->synchronizeControlCenter($controlCenter, $profiles, $urgencyThreshold, $notificationRule);

        return [
            'urgency_threshold' => $urgencyThreshold,
            'notification_rule' => $notificationRule,
            'past_match_weights' => $baseWeights,
            'past_match_weight_profiles' => $profiles,
            'control_center' => $controlCenter,
            'updated_at' => $attributes['updated_at'] ?? $defaults['updated_at'],
            'updated_by' => $attributes['updated_by'] ?? $defaults['updated_by'],
            'updated_by_name' => $attributes['updated_by_name'] ?? $defaults['updated_by_name'],
        ];
    }

    private function defaultControlCenter(array $baseWeights): array
    {
        $profiles = $this->deriveUrgencyProfiles($baseWeights);

        return [
            'matching' => [
                'engine_enabled' => true,
                'active_mode' => 'normal',
                'mode_weights' => [
                    'normal' => $profiles['medium'],
                    'emergency' => $profiles['critical'],
                    'manual_override' => $profiles['high'],
                ],
                'weights' => $profiles['medium'],
                'strict_blood_type' => true,
                'max_search_radius_km' => 35,
                'max_donor_notifications' => 8,
            ],
            'emergency' => [
                'urgency_threshold' => self::DEFAULT_URGENCY_THRESHOLD,
                'escalation_timer_minutes' => 5,
                'stage_1_label' => 'Nearby donors',
                'stage_2_label' => 'Expand radius',
                'stage_3_label' => 'Regional/National broadcast',
                'stage_2_radius_km' => 60,
                'stage_3_scope' => 'regional',
                'actions' => [
                    'increase_priority_weight' => true,
                    'expand_search_radius' => true,
                    'trigger_sms_fallback' => true,
                ],
            ],
            'notifications' => [
                'channels' => [
                    'sms' => true,
                    'email' => true,
                    'in_app' => true,
                ],
                'rule' => self::DEFAULT_NOTIFICATION_RULE,
                'retry_attempts' => 3,
                'batching' => 'wave-based',
                'quiet_hours' => [
                    'enabled' => false,
                    'start' => '22:00',
                    'end' => '06:00',
                ],
            ],
            'user_access' => [
                'role_permissions' => [
                    'admin' => 'full-control',
                    'hospital' => 'request-and-coordinate',
                    'donor' => 'respond-and-update',
                ],
                'session_timeout_minutes' => 30,
                'max_login_attempts' => 5,
                'ip_whitelisting' => '',
            ],
            'audit' => [
                'activity_logging' => true,
                'sensitive_action_logging' => true,
                'retention_days' => 90,
                'auto_reports' => 'weekly',
            ],
            'analytics' => [
                'aggregation' => 'daily',
                'matching_success_threshold' => 85,
                'target_response_time_minutes' => 15,
                'refresh_rate_seconds' => 60,
            ],
            'performance' => [
                'queue_processing_limit' => 250,
                'cache_duration_minutes' => 10,
                'api_rate_limit' => 120,
                'global_auto_refresh_seconds' => 45,
            ],
            'blood_request_rules' => [
                'minimum_units' => 1,
                'expiration_time_minutes' => 45,
                'duplicate_prevention' => true,
                'priority_override_permission' => 'admin-only',
            ],
            'geolocation' => [
                'default_search_radius_km' => 35,
                'region_prioritization' => 'local-first',
                'traffic_aware_routing' => true,
            ],
            'fail_safe' => [
                'matching_failure_fallback' => 'manual-assignment',
                'safe_mode' => false,
                'backup_frequency' => 'daily',
                'last_backup_timestamp' => null,
            ],
        ];
    }

    private function normalizeControlCenter(?array $controlCenter, array $defaults): array
    {
        $merged = is_array($controlCenter)
            ? array_replace_recursive($defaults, $controlCenter)
            : $defaults;

        $modeWeights = $this->normalizeModeWeights(
            $merged['matching']['mode_weights'] ?? [],
            $defaults['matching']['mode_weights']
        );
        $activeMode = $this->normalizeEnum(
            $merged['matching']['active_mode'] ?? 'normal',
            self::CONTROL_CENTER_MODES,
            'normal'
        );

        return [
            'matching' => [
                'engine_enabled' => (bool) ($merged['matching']['engine_enabled'] ?? $defaults['matching']['engine_enabled']),
                'active_mode' => $activeMode,
                'mode_weights' => $modeWeights,
                'weights' => $modeWeights[$activeMode],
                'strict_blood_type' => (bool) ($merged['matching']['strict_blood_type'] ?? $defaults['matching']['strict_blood_type']),
                'max_search_radius_km' => $this->clampInt($merged['matching']['max_search_radius_km'] ?? $defaults['matching']['max_search_radius_km'], 5, 100),
                'max_donor_notifications' => $this->clampInt($merged['matching']['max_donor_notifications'] ?? $defaults['matching']['max_donor_notifications'], 1, 25),
            ],
            'emergency' => [
                'urgency_threshold' => $this->clampInt($merged['emergency']['urgency_threshold'] ?? $defaults['emergency']['urgency_threshold'], 1, 100),
                'escalation_timer_minutes' => $this->clampInt($merged['emergency']['escalation_timer_minutes'] ?? $defaults['emergency']['escalation_timer_minutes'], 1, 60),
                'stage_1_label' => (string) ($merged['emergency']['stage_1_label'] ?? $defaults['emergency']['stage_1_label']),
                'stage_2_label' => (string) ($merged['emergency']['stage_2_label'] ?? $defaults['emergency']['stage_2_label']),
                'stage_3_label' => (string) ($merged['emergency']['stage_3_label'] ?? $defaults['emergency']['stage_3_label']),
                'stage_2_radius_km' => $this->clampInt($merged['emergency']['stage_2_radius_km'] ?? $defaults['emergency']['stage_2_radius_km'], 10, 250),
                'stage_3_scope' => $this->normalizeEnum($merged['emergency']['stage_3_scope'] ?? $defaults['emergency']['stage_3_scope'], ['regional', 'national'], $defaults['emergency']['stage_3_scope']),
                'actions' => [
                    'increase_priority_weight' => (bool) ($merged['emergency']['actions']['increase_priority_weight'] ?? $defaults['emergency']['actions']['increase_priority_weight']),
                    'expand_search_radius' => (bool) ($merged['emergency']['actions']['expand_search_radius'] ?? $defaults['emergency']['actions']['expand_search_radius']),
                    'trigger_sms_fallback' => (bool) ($merged['emergency']['actions']['trigger_sms_fallback'] ?? $defaults['emergency']['actions']['trigger_sms_fallback']),
                ],
            ],
            'notifications' => [
                'channels' => [
                    'sms' => (bool) ($merged['notifications']['channels']['sms'] ?? $defaults['notifications']['channels']['sms']),
                    'email' => (bool) ($merged['notifications']['channels']['email'] ?? $defaults['notifications']['channels']['email']),
                    'in_app' => (bool) ($merged['notifications']['channels']['in_app'] ?? $defaults['notifications']['channels']['in_app']),
                ],
                'rule' => $this->normalizeEnum($merged['notifications']['rule'] ?? $defaults['notifications']['rule'], ['critical-only', 'balanced', 'broadcast-all', 'emergency-active'], $defaults['notifications']['rule']),
                'retry_attempts' => $this->clampInt($merged['notifications']['retry_attempts'] ?? $defaults['notifications']['retry_attempts'], 1, 10),
                'batching' => $this->normalizeEnum($merged['notifications']['batching'] ?? $defaults['notifications']['batching'], ['wave-based', 'immediate-broadcast'], $defaults['notifications']['batching']),
                'quiet_hours' => [
                    'enabled' => (bool) ($merged['notifications']['quiet_hours']['enabled'] ?? $defaults['notifications']['quiet_hours']['enabled']),
                    'start' => $this->normalizeTime($merged['notifications']['quiet_hours']['start'] ?? $defaults['notifications']['quiet_hours']['start']),
                    'end' => $this->normalizeTime($merged['notifications']['quiet_hours']['end'] ?? $defaults['notifications']['quiet_hours']['end']),
                ],
            ],
            'user_access' => [
                'role_permissions' => [
                    'admin' => $this->normalizeEnum($merged['user_access']['role_permissions']['admin'] ?? $defaults['user_access']['role_permissions']['admin'], ['full-control', 'audit-only'], $defaults['user_access']['role_permissions']['admin']),
                    'hospital' => $this->normalizeEnum($merged['user_access']['role_permissions']['hospital'] ?? $defaults['user_access']['role_permissions']['hospital'], ['request-and-coordinate', 'request-only', 'view-only'], $defaults['user_access']['role_permissions']['hospital']),
                    'donor' => $this->normalizeEnum($merged['user_access']['role_permissions']['donor'] ?? $defaults['user_access']['role_permissions']['donor'], ['respond-and-update', 'respond-only', 'view-only'], $defaults['user_access']['role_permissions']['donor']),
                ],
                'session_timeout_minutes' => $this->clampInt($merged['user_access']['session_timeout_minutes'] ?? $defaults['user_access']['session_timeout_minutes'], 5, 480),
                'max_login_attempts' => $this->clampInt($merged['user_access']['max_login_attempts'] ?? $defaults['user_access']['max_login_attempts'], 3, 10),
                'ip_whitelisting' => trim((string) ($merged['user_access']['ip_whitelisting'] ?? $defaults['user_access']['ip_whitelisting'])),
            ],
            'audit' => [
                'activity_logging' => (bool) ($merged['audit']['activity_logging'] ?? $defaults['audit']['activity_logging']),
                'sensitive_action_logging' => (bool) ($merged['audit']['sensitive_action_logging'] ?? $defaults['audit']['sensitive_action_logging']),
                'retention_days' => (int) $this->normalizeEnum((string) ($merged['audit']['retention_days'] ?? $defaults['audit']['retention_days']), ['30', '90', '365'], (string) $defaults['audit']['retention_days']),
                'auto_reports' => $this->normalizeEnum($merged['audit']['auto_reports'] ?? $defaults['audit']['auto_reports'], ['disabled', 'daily', 'weekly'], $defaults['audit']['auto_reports']),
            ],
            'analytics' => [
                'aggregation' => $this->normalizeEnum($merged['analytics']['aggregation'] ?? $defaults['analytics']['aggregation'], ['hourly', 'daily', 'weekly'], $defaults['analytics']['aggregation']),
                'matching_success_threshold' => $this->clampInt($merged['analytics']['matching_success_threshold'] ?? $defaults['analytics']['matching_success_threshold'], 50, 100),
                'target_response_time_minutes' => $this->clampInt($merged['analytics']['target_response_time_minutes'] ?? $defaults['analytics']['target_response_time_minutes'], 1, 240),
                'refresh_rate_seconds' => $this->clampInt($merged['analytics']['refresh_rate_seconds'] ?? $defaults['analytics']['refresh_rate_seconds'], 15, 300),
            ],
            'performance' => [
                'queue_processing_limit' => $this->clampInt($merged['performance']['queue_processing_limit'] ?? $defaults['performance']['queue_processing_limit'], 25, 1000),
                'cache_duration_minutes' => $this->clampInt($merged['performance']['cache_duration_minutes'] ?? $defaults['performance']['cache_duration_minutes'], 1, 1440),
                'api_rate_limit' => $this->clampInt($merged['performance']['api_rate_limit'] ?? $defaults['performance']['api_rate_limit'], 10, 2000),
                'global_auto_refresh_seconds' => $this->clampInt($merged['performance']['global_auto_refresh_seconds'] ?? $defaults['performance']['global_auto_refresh_seconds'], 15, 300),
            ],
            'blood_request_rules' => [
                'minimum_units' => $this->clampInt($merged['blood_request_rules']['minimum_units'] ?? $defaults['blood_request_rules']['minimum_units'], 1, 20),
                'expiration_time_minutes' => $this->clampInt($merged['blood_request_rules']['expiration_time_minutes'] ?? $defaults['blood_request_rules']['expiration_time_minutes'], 5, 1440),
                'duplicate_prevention' => (bool) ($merged['blood_request_rules']['duplicate_prevention'] ?? $defaults['blood_request_rules']['duplicate_prevention']),
                'priority_override_permission' => $this->normalizeEnum($merged['blood_request_rules']['priority_override_permission'] ?? $defaults['blood_request_rules']['priority_override_permission'], ['admin-only', 'hospital-and-admin', 'disabled'], $defaults['blood_request_rules']['priority_override_permission']),
            ],
            'geolocation' => [
                'default_search_radius_km' => $this->clampInt($merged['geolocation']['default_search_radius_km'] ?? $defaults['geolocation']['default_search_radius_km'], 5, 100),
                'region_prioritization' => $this->normalizeEnum($merged['geolocation']['region_prioritization'] ?? $defaults['geolocation']['region_prioritization'], ['local-first', 'regional-balance', 'national-reach'], $defaults['geolocation']['region_prioritization']),
                'traffic_aware_routing' => (bool) ($merged['geolocation']['traffic_aware_routing'] ?? $defaults['geolocation']['traffic_aware_routing']),
            ],
            'fail_safe' => [
                'matching_failure_fallback' => $this->normalizeEnum($merged['fail_safe']['matching_failure_fallback'] ?? $defaults['fail_safe']['matching_failure_fallback'], ['manual-assignment', 'broadcast-all-donors'], $defaults['fail_safe']['matching_failure_fallback']),
                'safe_mode' => (bool) ($merged['fail_safe']['safe_mode'] ?? $defaults['fail_safe']['safe_mode']),
                'backup_frequency' => $this->normalizeEnum($merged['fail_safe']['backup_frequency'] ?? $defaults['fail_safe']['backup_frequency'], ['daily', 'weekly'], $defaults['fail_safe']['backup_frequency']),
                'last_backup_timestamp' => $this->normalizeDateTime($merged['fail_safe']['last_backup_timestamp'] ?? $defaults['fail_safe']['last_backup_timestamp']),
            ],
        ];
    }

    private function normalizeModeWeights(array $modeWeights, array $fallbackWeights): array
    {
        return collect(self::CONTROL_CENTER_MODES)
            ->mapWithKeys(function (string $mode) use ($modeWeights, $fallbackWeights) {
                return [
                    $mode => $this->normalizeWeights($modeWeights[$mode] ?? $fallbackWeights[$mode]),
                ];
            })
            ->all();
    }

    private function mapModeWeightsToProfiles(array $modeWeights, array $fallbackBaseWeights): array
    {
        $normalizedModes = $this->normalizeModeWeights($modeWeights, $this->defaultControlCenter($fallbackBaseWeights)['matching']['mode_weights']);
        $derivedLow = $this->deriveUrgencyProfiles($normalizedModes['normal'])['low'];

        return [
            'low' => $derivedLow,
            'medium' => $normalizedModes['normal'],
            'high' => $normalizedModes['manual_override'],
            'critical' => $normalizedModes['emergency'],
        ];
    }

    private function synchronizeControlCenter(array $controlCenter, array $profiles, int $urgencyThreshold, string $notificationRule): array
    {
        $controlCenter['matching']['mode_weights'] = [
            'normal' => $profiles['medium'],
            'emergency' => $profiles['critical'],
            'manual_override' => $profiles['high'],
        ];

        $activeMode = $this->normalizeEnum($controlCenter['matching']['active_mode'] ?? 'normal', self::CONTROL_CENTER_MODES, 'normal');

        $controlCenter['matching']['active_mode'] = $activeMode;
        $controlCenter['matching']['weights'] = $controlCenter['matching']['mode_weights'][$activeMode];
        $controlCenter['emergency']['urgency_threshold'] = $urgencyThreshold;
        $controlCenter['notifications']['rule'] = $notificationRule;

        return $controlCenter;
    }

    private function normalizeWeightProfiles(null|array $profiles, array $fallbackProfiles): array
    {
        if (! is_array($profiles) || $profiles === []) {
            return $fallbackProfiles;
        }

        return collect(self::URGENCY_LEVELS)
            ->mapWithKeys(function (string $level) use ($profiles, $fallbackProfiles) {
                $profile = $profiles[$level] ?? $fallbackProfiles[$level];

                return [$level => $this->normalizeWeights(is_array($profile) ? $profile : $fallbackProfiles[$level])];
            })
            ->all();
    }

    private function deriveUrgencyProfiles(array $baseWeights): array
    {
        return collect(self::URGENCY_PROFILE_MULTIPLIERS)
            ->map(fn (array $multipliers) => $this->normalizeWeights([
                'priority' => $baseWeights['priority'] * $multipliers['priority'],
                'availability' => $baseWeights['availability'] * $multipliers['availability'],
                'distance' => $baseWeights['distance'] * $multipliers['distance'],
                'time' => $baseWeights['time'] * $multipliers['time'],
            ]))
            ->all();
    }

    private function normalizeWeights(array $weights): array
    {
        $merged = array_merge(self::DEFAULT_PAST_MATCH_WEIGHTS, $weights);
        $clamped = collect($merged)
            ->only(array_keys(self::DEFAULT_PAST_MATCH_WEIGHTS))
            ->map(fn ($value) => max(0.0, min(1.0, (float) $value)))
            ->all();

        $sum = array_sum($clamped);

        if ($sum <= 0) {
            return self::DEFAULT_PAST_MATCH_WEIGHTS;
        }

        return collect($clamped)
            ->map(fn (float $value) => round($value / $sum, 4))
            ->all();
    }

    private function syncCache(array $settings): void
    {
        Cache::forever(self::CACHE_KEY, $settings);
    }

    private function normalizeUrgencyLevel(string $urgencyLevel): string
    {
        $normalized = strtolower(trim($urgencyLevel));

        return in_array($normalized, self::URGENCY_LEVELS, true)
            ? $normalized
            : 'medium';
    }

    private function normalizeEnum(mixed $value, array $allowed, mixed $fallback): mixed
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function clampInt(mixed $value, int $min, int $max): int
    {
        return max($min, min($max, (int) $value));
    }

    private function normalizeTime(mixed $value): string
    {
        $candidate = is_string($value) ? trim($value) : '';

        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $candidate) === 1
            ? $candidate
            : '00:00';
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : date(DATE_ATOM, $timestamp);
    }

    private function storedProfilesAvailable(): bool
    {
        return $this->tableAvailable() && Schema::hasColumn('system_settings', 'past_match_weight_profiles');
    }

    private function controlCenterAvailable(): bool
    {
        return $this->tableAvailable() && Schema::hasColumn('system_settings', 'control_center');
    }

    private function tableAvailable(): bool
    {
        return Schema::hasTable('system_settings');
    }
}