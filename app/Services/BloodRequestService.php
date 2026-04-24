<?php

namespace App\Services;

use App\Models\BloodRequest;

class BloodRequestService
{
    private const WORKFLOW_TRANSITIONS = [
        'pending' => ['matching', 'cancelled'],
        'matching' => ['matched', 'confirmed', 'completed', 'fulfilled', 'cancelled'],
        'matched' => ['confirmed', 'completed', 'fulfilled', 'cancelled'],
        'confirmed' => ['completed', 'fulfilled', 'cancelled'],
        'completed' => ['fulfilled'],
        'fulfilled' => [],
        'cancelled' => [],
    ];

    /**
     * Donor blood types that are compatible with each requested type.
     * Key = blood type needed by the hospital.
     * Value = donor types that can safely donate.
     */
    private const COMPATIBILITY_MAP = [
        'A+'  => ['A+', 'A-', 'O+', 'O-'],
        'A-'  => ['A-', 'O-'],
        'B+'  => ['B+', 'B-', 'O+', 'O-'],
        'B-'  => ['B-', 'O-'],
        'AB+' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
        'AB-' => ['A-', 'B-', 'AB-', 'O-'],
        'O+'  => ['O+', 'O-'],
        'O-'  => ['O-'],
    ];

    /**
     * Generate a unique, human-readable case identifier.
     * Format: BR-YYYYMMDD-XXXXX  (X = uppercase alphanumeric)
     */
    public function generateCaseId(): string
    {
        return sprintf(
            'BR-%s-%s',
            now()->format('Ymd'),
            strtoupper(substr(uniqid('', false), -5))
        );
    }

    /**
     * Return donor blood types that are compatible with the requested type.
     *
     * @return string[]
     */
    public function getCompatibleDonorBloodTypes(string $requestedBloodType): array
    {
        return self::COMPATIBILITY_MAP[strtoupper($requestedBloodType)] ?? [];
    }

    /**
     * Validate that a blood type string is a known ABO/Rh value.
     */
    public function isValidBloodType(string $bloodType): bool
    {
        return array_key_exists(strtoupper($bloodType), self::COMPATIBILITY_MAP);
    }

    /**
     * Determine whether a request qualifies as an emergency based on
     * its resolved urgency, an explicit caller override, and system disaster mode.
     */
    public function resolveIsEmergency(
        string $resolvedUrgency,
        bool   $requestedEmergency,
        bool   $disasterModeActive
    ): bool {
        return $requestedEmergency
            || in_array($resolvedUrgency, ['high', 'critical'], true)
            || $disasterModeActive;
    }

    /**
     * Re-derive and persist tracking counters from actual relation records.
     * Call after donors respond or donations are confirmed.
     */
    public function syncTrackingCounts(BloodRequest $bloodRequest): void
    {
        $matched    = $bloodRequest->matches()->count();
        $responses  = $bloodRequest->donorResponses()->count();
        $accepted   = $bloodRequest->donorResponses()->where('response', 'accepted')->count();
        $fulfilled  = (int) $bloodRequest->donationHistories()->sum('units');

        $bloodRequest->updateQuietly([
            'matched_donors_count' => $matched,
            'responses_received'   => $responses,
            'accepted_donors'      => $accepted,
            'fulfilled_units'      => max(0, $fulfilled),
        ]);
    }

    /**
     * Increment the notifications_sent counter atomically.
     */
    public function incrementNotificationsSent(BloodRequest $bloodRequest, int $count = 1): void
    {
        $bloodRequest->increment('notifications_sent', $count);
    }

    /**
     * Validate whether a request can move from its current status to the target status.
     * Returns null when the transition is valid, otherwise a user-facing rejection message.
     */
    public function invalidTransitionReason(BloodRequest $bloodRequest, string $targetStatus): ?string
    {
        $currentStatus = strtolower((string) ($bloodRequest->status ?: 'pending'));
        $targetStatus = strtolower($targetStatus);

        if ($currentStatus === $targetStatus) {
            return null;
        }

        $allowedTargets = self::WORKFLOW_TRANSITIONS[$currentStatus] ?? [];

        if (! in_array($targetStatus, $allowedTargets, true)) {
            return sprintf(
                'Cannot transition request from %s to %s.',
                $currentStatus,
                $targetStatus
            );
        }

        if (in_array($targetStatus, ['completed', 'fulfilled'], true) && ! $this->hasAcceptedDonor($bloodRequest)) {
            return 'Cannot mark request as completed or fulfilled before a donor accepts the request.';
        }

        return null;
    }

    private function hasAcceptedDonor(BloodRequest $bloodRequest): bool
    {
        if ((int) ($bloodRequest->accepted_donors ?? 0) > 0) {
            return true;
        }

        return $bloodRequest->donorResponses()
            ->where('response', 'accepted')
            ->exists();
    }
}
