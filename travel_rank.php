<?php
require __DIR__ . "/vendor/autoload.php";

$user1 = App\Models\User::factory()->create(['role' => 'donor']);
$user2 = App\Models\User::factory()->create(['role' => 'donor']);
$baseReliable = App\Models\Donor::create(['user_id' => $user1->id, 'name' => 'Base Reliable', 'blood_type' => 'A+', 'city' => 'Manila', 'contact_number' => '09170000001', 'email' => $user1->email, 'password' => 'password', 'availability' => true, 'privacy_consent_at' => now(), 'reliability_score' => 95, 'latitude' => 14.5995, 'longitude' => 120.9842]);
$fastArrival = App\Models\Donor::create(['user_id' => $user2->id, 'name' => 'Fast Arrival', 'blood_type' => 'A+', 'city' => 'Manila', 'contact_number' => '09170000002', 'email' => $user2->email, 'password' => 'password', 'availability' => true, 'privacy_consent_at' => now(), 'reliability_score' => 70, 'latitude' => 14.6715, 'longitude' => 120.9842]);
$candidates = collect([
    ['donor' => $baseReliable, 'distance_km' => 1.0, 'estimated_travel_minutes' => 90.0, 'traffic_condition' => 'heavy', 'traffic_multiplier' => 1.8, 'transport_accessibility_score' => 70.0, 'fastest_arrival_score' => 10.0],
    ['donor' => $fastArrival, 'distance_km' => 40.0, 'estimated_travel_minutes' => 70.0, 'traffic_condition' => 'heavy', 'traffic_multiplier' => 1.7, 'transport_accessibility_score' => 20.0, 'fastest_arrival_score' => 100.0],
]);
$pastMatch = app(App\Algorithms\PASTMatch::class);
$baseline = $pastMatch->rankDonors($candidates);
echo "baseline top: " . $baseline->first()['donor']->name . "\n";
$app = app(App\Services\EmergencyBroadcastModeService::class);
$app->activate('earthquake', null);
$ranked = $pastMatch->rankDonors($candidates);
echo "emergency top: " . $ranked->first()['donor']->name . "\n";
foreach ($ranked as $item) {
    echo $item['donor']->name . ' -> base:' . $item['base_score'] . ' adj:' . $item['emergency_adjustment'] . ' op:' . $item['operational_score'] . "\n";
}
