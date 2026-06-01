<?php
require __DIR__ . "/vendor/autoload.php";
$service = new App\Services\TravelIntelligenceService();
$closer = $service->analyze(4.44, "Manila", "Quezon City", true);
$farther = $service->analyze(8.0, "Manila", "Manila", true);
echo "closer: " . json_encode($closer) . "\n";
echo "farther: " . json_encode($farther) . "\n";
