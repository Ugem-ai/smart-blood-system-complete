<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$svc = $app->make(App\Services\SystemSettingsService::class);
$settings = $svc->update([
    'urgency_threshold' => 82,
    'notification_rule' => 'balanced',
    'weights' => [
        'priority' => 0.34,
        'availability' => 0.22,
        'distance' => 0.18,
        'time' => 0.26,
    ],
    'weight_profiles' => [
        'low' => ['priority' => 0.18, 'availability' => 0.27, 'distance' => 0.31, 'time' => 0.24],
        'medium' => ['priority' => 0.34, 'availability' => 0.22, 'distance' => 0.18, 'time' => 0.26],
        'high' => ['priority' => 0.29, 'availability' => 0.17, 'distance' => 0.16, 'time' => 0.38],
        'critical' => ['priority' => 0.32, 'availability' => 0.14, 'distance' => 0.12, 'time' => 0.42],
    ],
], null);
print_r($settings['past_match_weight_profiles']);
print_r($settings['past_match_weights']);
print_r($settings['past_match_weights_v2']);
