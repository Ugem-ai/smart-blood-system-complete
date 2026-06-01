<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== SMS DIAGNOSTIC REPORT ===\n\n";

$notificationService = app(\App\Services\NotificationService::class);

// Check configuration
$health = $notificationService->notificationHealth();
echo "NOTIFICATION HEALTH:\n";
echo "- Push configured: " . ($health['push_configured'] ? 'YES' : 'NO') . "\n";
echo "- SMS configured: " . ($health['sms_configured'] ? 'YES' : 'NO') . "\n";
echo "- Overall ready: " . ($health['ready'] ? 'YES' : 'NO') . "\n\n";

// Check UniSMS config values
$unismsApiKey = config('services.unisms.api_key');
$unismsSenderId = config('services.unisms.sender_id');
$unismsEndpoint = config('services.unisms.endpoint');

echo "UNISMS CONFIGURATION:\n";
echo "- API Key: " . (empty($unismsApiKey) ? 'NOT SET' : 'SET (' . substr($unismsApiKey, 0, 8) . '...)') . "\n";
echo "- Sender ID: " . (empty($unismsSenderId) ? 'NOT SET' : 'SET (' . $unismsSenderId . ')') . "\n";
echo "- Endpoint: " . (empty($unismsEndpoint) ? 'NOT SET' : $unismsEndpoint) . "\n\n";

// Check recent SMS deliveries
$smsDeliveries = \App\Models\NotificationDelivery::where('channel', 'sms')
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get();

echo "RECENT SMS DELIVERIES:\n";
if ($smsDeliveries->isEmpty()) {
    echo "No SMS deliveries found in database.\n";
} else {
    foreach ($smsDeliveries as $delivery) {
        echo "- ID: {$delivery->id}, Status: {$delivery->status}, Type: {$delivery->type}, Created: {$delivery->created_at}\n";
        if ($delivery->response && isset($delivery->response['reason'])) {
            echo "  Reason: {$delivery->response['reason']}\n";
        }
    }
}
echo "\n";

// Test SMS sending (if configured)
$testRecipient = env('UNISMS_TEST_RECIPIENT');
if ($health['sms_configured'] && $testRecipient) {
    echo "TESTING SMS SEND:\n";
    $testResult = $notificationService->sendSms(
        userId: null,
        type: 'diagnostic_test',
        to: $testRecipient,
        message: 'Smart Blood System SMS Test - ' . now()->format('Y-m-d H:i:s'),
        meta: ['test' => true]
    );
    echo "- Test SMS sent: " . ($testResult ? 'SUCCESS' : 'FAILED') . "\n";
} elseif ($health['sms_configured']) {
    echo "SMS is configured but no test recipient is set.\n";
    echo "Set UNISMS_TEST_RECIPIENT in your .env file to run a live SMS test.\n";
} else {
    echo "SMS NOT CONFIGURED - Cannot test sending.\n";
    echo "To enable SMS:\n";
    echo "1. Sign up for UniSMS at https://unismsapi.com/\n";
    echo "2. Get API key and sender ID\n";
    echo "3. Set UNISMS_API_KEY, UNISMS_SENDER_ID, and optionally UNISMS_ENDPOINT in .env file\n";
}

echo "\n=== END REPORT ===\n";