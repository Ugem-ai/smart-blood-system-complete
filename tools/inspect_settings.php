<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$svc = $app->make(App\Services\SystemSettingsService::class);
// Clear cached settings to ensure defaults/current reflect code changes
$app->make(Illuminate\Support\Facades\Cache::class)::forget('system:settings:v1');
$settings = $svc->current();
print_r($settings['past_match_weight_profiles']);
print_r($settings['past_match_weights']);
print_r($settings['past_match_weights_v2']);
