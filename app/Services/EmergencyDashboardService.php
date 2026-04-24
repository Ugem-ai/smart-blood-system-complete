<?php

namespace App\Services;

use App\Models\BloodRequest;
use App\Models\DonationHistory;
use App\Models\DonorAlertLog;
use App\Models\DonorRequestResponse;

class EmergencyDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        $liveStatuses = ['pending', 'matching', 'open'];
        $alertsWindowStart = now()->subMinutes(30);

        $liveRequestsQuery = BloodRequest::query()
            ->whereIn('status', $liveStatuses)
            ->latest();

        $activeAlertsQuery = DonorAlertLog::query()
            ->with([
                'donor:id,name,blood_type',
                'bloodRequest:id,hospital_name,blood_type,status',
            ])
            ->where('sent_at', '>=', $alertsWindowStart)
            ->latest('sent_at');

        $acceptedResponsesQuery = DonorRequestResponse::query()
            ->where('response', 'accepted')
            ->latest('responded_at');

        $completedDonationsQuery = DonationHistory::query()
            ->with([
                'donor:id,name,blood_type',
                'hospital:id,hospital_name',
            ])
            ->where('status', 'completed')
            ->latest('donated_at');

        return [
            'updated_at' => now()->toIso8601String(),
            'counts' => [
                'live_blood_requests' => (int) $liveRequestsQuery->count(),
                'active_donor_alerts' => (int) $activeAlertsQuery->count(),
                'accepted_requests' => (int) $acceptedResponsesQuery->count(),
                'donations_completed' => (int) $completedDonationsQuery->count(),
            ],
            'live_blood_requests' => $liveRequestsQuery
                ->limit($limit)
                ->get(['id', 'hospital_name', 'blood_type', 'city', 'urgency_level', 'status', 'created_at']),
            'active_donor_alerts' => $activeAlertsQuery
                ->limit($limit)
                ->get(['id', 'blood_request_id', 'donor_id', 'escalation_level', 'channel', 'sent_at']),
            'accepted_requests' => $acceptedResponsesQuery
                ->limit($limit)
                ->get(['id', 'blood_request_id', 'donor_id', 'response', 'responded_at']),
            'donations_completed_list' => $completedDonationsQuery
                ->limit($limit)
                ->get(['id', 'donor_id', 'hospital_id', 'request_id', 'units', 'donated_at']),
        ];
    }
}
