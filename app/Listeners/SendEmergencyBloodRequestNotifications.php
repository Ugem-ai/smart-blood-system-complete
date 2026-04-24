<?php

namespace App\Listeners;

use App\Events\EmergencyBloodRequestEvent;
use App\Services\NotificationService;

class SendEmergencyBloodRequestNotifications
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public function handle(EmergencyBloodRequestEvent $event): void
    {
        foreach ($event->rankedMatches as $match) {
            $this->notificationService->sendDonorAlert(
                donor: $match['donor'],
                bloodRequest: $event->bloodRequest,
                distanceKm: $match['distance_km'] ?? null
            );
        }
    }
}
