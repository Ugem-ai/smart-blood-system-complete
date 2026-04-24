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

// Check Twilio config values
$twilioSid = config('services.twilio.sid');
$twilioToken = config('services.twilio.token');
$twilioFrom = config('services.twilio.from');

echo "TWILIO CONFIGURATION:\n";
echo "- SID: " . (empty($twilioSid) ? 'NOT SET' : 'SET (' . substr($twilioSid, 0, 8) . '...)') . "\n";
echo "- Token: " . (empty($twilioToken) ? 'NOT SET' : 'SET (' . substr($twilioToken, 0, 8) . '...)') . "\n";
echo "- From: " . (empty($twilioFrom) ? 'NOT SET' : 'SET (' . $twilioFrom . ')') . "\n\n";

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
if ($health['sms_configured']) {
    echo "TESTING SMS SEND:\n";
    $testResult = $notificationService->sendSms(
        userId: null,
        type: 'diagnostic_test',
        to: $twilioFrom, // Send to ourselves for testing
        message: 'Smart Blood System SMS Test - ' . now()->format('Y-m-d H:i:s'),
        meta: ['test' => true]
    );
    echo "- Test SMS sent: " . ($testResult ? 'SUCCESS' : 'FAILED') . "\n";
} else {
    echo "SMS NOT CONFIGURED - Cannot test sending.\n";
    echo "To enable SMS:\n";
    echo "1. Sign up for Twilio account at https://www.twilio.com/\n";
    echo "2. Get SID, Auth Token, and phone number\n";
    echo "3. Set TWILIO_SID, TWILIO_AUTH_TOKEN, TWILIO_FROM in .env file\n";
}

echo "\n=== END REPORT ===\n";