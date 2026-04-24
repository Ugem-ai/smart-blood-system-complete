<?php
// Diagnostic script for PAST-Match debugging

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "\n========================================\n";
echo "PAST-MATCH SYSTEM DIAGNOSTIC\n";
echo "========================================\n\n";

// ============================================
// STEP 1: VERIFY DATABASE DATA
// ============================================
echo "STEP 1: DATABASE VERIFICATION\n";
echo "--------------------------------------\n";

$request = \App\Models\BloodRequest::first();
if (!$request) {
    echo "ERROR: No blood requests found in database!\n";
    exit(1);
}

echo "✓ Blood Request Found:\n";
echo "  ID: " . $request->id . "\n";
echo "  Case ID: " . $request->case_id . "\n";
echo "  Blood Type: " . $request->blood_type . "\n";
echo "  Hospital: " . $request->hospital_name . "\n";
echo "  Urgency: " . $request->urgency_level . "\n";
echo "  Latitude: " . ($request->latitude ?? 'NULL') . "\n";
echo "  Longitude: " . ($request->longitude ?? 'NULL') . "\n";
echo "  City: " . ($request->city ?? 'NULL') . "\n";
echo "  Distance Limit (km): " . ($request->distance_limit_km ?? 50) . "\n";
echo "  Status: " . $request->status . "\n\n";

$donorCount = \App\Models\Donor::count();
echo "Total Donors in Database: " . $donorCount . "\n";
if ($donorCount === 0) {
    echo "WARNING: No donors seeded! Matching will fail.\n";
}
echo "\n";

// ============================================
// STEP 2: TEST DONOR FILTERING
// ============================================
echo "STEP 2: DONOR FILTERING SERVICE\n";
echo "--------------------------------------\n";

try {
    $donorFilterService = app(\App\Services\DonorFilterService::class);
    
    $filteredDonors = $donorFilterService->filterForRequest(
        requestedBloodType: (string) $request->blood_type,
        requestLatitude: $request->latitude !== null ? (float) $request->latitude : null,
        requestLongitude: $request->longitude !== null ? (float) $request->longitude : null,
        distanceLimitKm: (int) round((float) ($request->distance_limit_km ?? 50)),
        requestCity: $request->city,
        excludingRequestId: $request->id,
    );
    
    echo "✓ Filtering executed successfully\n";
    echo "  Eligible donors found: " . $filteredDonors->count() . "\n";
    
    if ($filteredDonors->count() > 0) {
        echo "  Sample donors:\n";
        $filteredDonors->take(3)->each(function($item) {
            $d = $item['donor'];
            echo "    - " . $d->name . " (Blood: " . $d->blood_type . ", Distance: " . ($item['distance_km'] ?? 'N/A') . " km)\n";
        });
    } else {
        echo "  WARNING: No donors passed filtering! Check:\n";
        echo "    - Blood type compatibility\n";
        echo "    - Donor availability status\n";
        echo "    - Distance limits\n";
        echo "    - Blood Request coordinates: " . ($request->latitude && $request->longitude ? "YES" : "NO (NULL - using city filter)") . "\n";
    }
    echo "\n";
} catch (\Exception $e) {
    echo "ERROR in DonorFilterService: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

// ============================================
// STEP 3: TEST PAST-MATCH RANKING
// ============================================
echo "STEP 3: PAST-MATCH ALGORITHM\n";
echo "--------------------------------------\n";

try {
    $pastMatch = app(\App\Algorithms\PASTMatch::class);
    
    $rankedDonors = $pastMatch->rankDonors($filteredDonors, [
        'urgency_level' => $request->urgency_level,
    ]);
    
    echo "✓ Ranking executed successfully\n";
    echo "  Ranked candidates: " . $rankedDonors->count() . "\n";
    
    if ($rankedDonors->count() > 0) {
        echo "  Top 5 ranked donors:\n";
        $rankedDonors->take(5)->each(function($item, $idx) {
            $d = $item['donor'];
            $score = $item['operational_score'] ?? $item['score'] ?? 'N/A';
            echo "    " . ($idx + 1) . ". " . $d->name . " - Score: " . $score . " (Distance: " . ($item['distance_km'] ?? 'N/A') . " km)\n";
        });
    } else {
        echo "  WARNING: Ranking returned 0 results!\n";
        echo "  Check PASTMatch algorithm logic.\n";
    }
    echo "\n";
} catch (\Exception $e) {
    echo "ERROR in PASTMatch: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

// ============================================
// STEP 4: TEST CONTROLLER ENDPOINT
// ============================================
echo "STEP 4: API CONTROLLER RESPONSE\n";
echo "--------------------------------------\n";

try {
    $controller = new \App\Http\Controllers\Api\AdminPanelController();
    $donorFilterService = app(\App\Services\DonorFilterService::class);
    $pastMatch = app(\App\Algorithms\PASTMatch::class);
    $notificationService = app(\App\Services\NotificationService::class);
    
    $httpRequest = new \Illuminate\Http\Request();
    
    $response = $controller->pastMatchDetails(
        $request,
        $donorFilterService,
        $pastMatch,
        $notificationService
    );
    
    $data = json_decode($response->getContent(), true);
    
    echo "✓ Controller response generated\n";
    echo "  Status: " . $response->getStatusCode() . "\n";
    echo "  Has data.ranked_donors: " . (isset($data['data']['ranked_donors']) ? 'YES' : 'NO') . "\n";
    
    if (isset($data['data']['ranked_donors'])) {
        echo "  Ranked donors count: " . count($data['data']['ranked_donors']) . "\n";
    }
    
    echo "  Has data.timeline: " . (isset($data['data']['timeline']) ? 'YES' : 'NO') . "\n";
    echo "  Has data.analytics: " . (isset($data['data']['analytics']) ? 'YES' : 'NO') . "\n";
    echo "  Has data.overview: " . (isset($data['data']['overview']) ? 'YES' : 'NO') . "\n";
    
    if (isset($data['data']['overview'])) {
        echo "  Overview:\n";
        foreach ($data['data']['overview'] as $key => $value) {
            echo "    - $key: $value\n";
        }
    }
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
echo "DIAGNOSTIC COMPLETE - ALL SYSTEMS GO\n";
echo "========================================\n\n";
echo "Next step: Verify in browser console that API returns this data.\n";
