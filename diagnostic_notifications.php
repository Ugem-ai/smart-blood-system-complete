<?php
// Diagnostic script for Notifications module

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "\n========================================\n";
echo "NOTIFICATIONS SYSTEM DIAGNOSTIC\n";
echo "========================================\n\n";

// ============================================
// STEP 1: VERIFY REQUEST DATA
// ============================================
echo "STEP 1: REQUEST VERIFICATION\n";
echo "--------------------------------------\n";

$request = \App\Models\BloodRequest::first();
if (!$request) {
    echo "ERROR: No blood requests found!\n";
    exit(1);
}

echo "✓ Blood Request Found:\n";
echo "  ID: " . $request->id . "\n";
echo "  Case ID: " . $request->case_id . "\n";
echo "  Hospital: " . $request->hospital_name . "\n";
echo "  Status: " . $request->status . "\n\n";

// ============================================
// STEP 2: CHECK ALERTS & RESPONSES
// ============================================
echo "STEP 2: DONOR ALERTS & RESPONSES\n";
echo "--------------------------------------\n";

$alerts = \App\Models\DonorAlertLog::where('blood_request_id', $request->id)
    ->with('donor')
    ->orderByDesc('sent_at')
    ->get();

$responses = \App\Models\DonorRequestResponse::where('blood_request_id', $request->id)
    ->with('donor')
    ->orderByDesc('responded_at')
    ->get();

echo "Alerts sent: " . $alerts->count() . "\n";
if ($alerts->count() > 0) {
    echo "  Sample alerts:\n";
    $alerts->take(3)->each(function($alert) {
        echo "    - " . $alert->donor->name . " (sent: " . ($alert->sent_at?->format('Y-m-d H:i:s') ?? 'N/A') . ")\n";
    });
}

echo "Donor responses: " . $responses->count() . "\n";
if ($responses->count() > 0) {
    echo "  Sample responses:\n";
    $responses->take(3)->each(function($resp) {
        echo "    - " . $resp->donor->name . " (" . $resp->response . " at " . ($resp->responded_at?->format('Y-m-d H:i:s') ?? 'N/A') . ")\n";
    });
}
echo "\n";

// ============================================
// STEP 3: CHECK NOTIFICATION DELIVERIES
// ============================================
echo "STEP 3: NOTIFICATION DELIVERIES\n";
echo "--------------------------------------\n";

$userIds = $alerts
    ->pluck('donor.user_id')
    ->merge($responses->pluck('donor.user_id'))
    ->filter()
    ->unique()
    ->values();

$deliveries = \App\Models\NotificationDelivery::query()
    ->when($userIds->isNotEmpty(), fn ($q) => $q->whereIn('user_id', $userIds->all()))
    ->where('sent_at', '>=', $request->created_at->copy()->subDay())
    ->latest('sent_at')
    ->get();

echo "Deliveries found: " . $deliveries->count() . "\n";
if ($deliveries->count() > 0) {
    echo "  Sample deliveries:\n";
    $deliveries->take(3)->each(function($del) {
        echo "    - " . $del->channel . " to user " . $del->user_id . " (" . $del->status . ")\n";
    });
} else {
    echo "  WARNING: No notification deliveries found.\n";
    echo "  Check: Are DonorAlertLog records being created?\n";
}
echo "\n";

// ============================================
// STEP 4: TEST CONTROLLER ENDPOINT
// ============================================
echo "STEP 4: API CONTROLLER RESPONSE\n";
echo "--------------------------------------\n";

try {
    $controller = new \App\Http\Controllers\Api\AdminPanelController();
    
    $response = $controller->notificationDashboard($request);
    
    $data = json_decode($response->getContent(), true);
    
    echo "✓ Controller response generated\n";
    echo "  Status: " . $response->getStatusCode() . "\n";
    echo "  Has data.request: " . (isset($data['data']['request']) ? 'YES' : 'NO') . "\n";
    echo "  Has data.notification_stream: " . (isset($data['data']['notification_stream']) ? 'YES' : 'NO') . "\n";
    
    if (isset($data['data']['notification_stream'])) {
        echo "  Stream entries: " . count($data['data']['notification_stream']) . "\n";
    }
    
    echo "  Has data.summary: " . (isset($data['data']['summary']) ? 'YES' : 'NO') . "\n";
    echo "  Has data.engagement_insights: " . (isset($data['data']['engagement_insights']) ? 'YES' : 'NO') . "\n";
    echo "  Has data.analytics: " . (isset($data['data']['analytics']) ? 'YES' : 'NO') . "\n";
    echo "\n";
} catch (\Exception $e) {
    echo "ERROR in Controller: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

// ============================================
// SUMMARY
// ============================================
echo "========================================\n";
echo "DIAGNOSTIC COMPLETE\n";
echo "========================================\n\n";

if ($alerts->count() === 0 && $responses->count() === 0) {
    echo "⚠️  WARNING: No alerts or responses exist for this request.\n";
    echo "This means no donors have been notified yet.\n";
    echo "Check if the notification queue is running or if matching has occurred.\n";
} else {
    echo "✓ Data exists. Verify in browser that API returns this data.\n";
}
