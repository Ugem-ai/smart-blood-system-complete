<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$delivery = \App\Models\NotificationDelivery::where('type', 'diagnostic_test')
    ->latest()
    ->first();

if ($delivery) {
    echo "LATEST DIAGNOSTIC TEST SMS:\n";
    echo "- ID: {$delivery->id}\n";
    echo "- Status: {$delivery->status}\n";
    echo "- Created: {$delivery->created_at}\n";
    echo "- Duration: {$delivery->duration_ms}ms\n";

    if ($delivery->response) {
        echo "- Response Details:\n";
        echo json_encode($delivery->response, JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "No diagnostic test SMS found.\n";
}