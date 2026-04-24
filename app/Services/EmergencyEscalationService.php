<?php

namespace App\Services;

use App\Models\BloodRequest;
use App\Models\DonorAlertLog;
use Illuminate\Support\Collection;

class EmergencyEscalationService
{
    public const LEVEL_CLOSEST = 1;
    public const LEVEL_WIDER_RADIUS = 2;
    public const LEVEL_ALL_COMPATIBLE = 3;

    private const MAX_FILTER_RADIUS_KM = 500;

    public function __construct(private readonly DonorFilterService $donorFilterService)
    {
    }

    public function shouldEscalate(BloodRequest $bloodRequest): bool
    {
        if (in_array($bloodRequest->status, ['completed', 'cancelled'], true)) {
            return false;
        }

        $hasAccepted = $bloodRequest->donorResponses()
            ->where('response', 'accepted')
            ->exists();

        return ! $hasAccepted;
    }

    /**
     * @return Collection<int, array{donor: \App\Models\Donor, distance_km: float|null}>
     */
    public function recipientsForLevel(BloodRequest $bloodRequest, int $level): Collection
    {
        $all = $this->allCompatibleCandidates($bloodRequest);

        $alreadyAlertedDonorIds = DonorAlertLog::query()
            ->where('blood_request_id', $bloodRequest->id)
            ->pluck('donor_id')
            ->all();

        $isAlerted = static fn (array $candidate): bool => in_array($candidate['donor']->id, $alreadyAlertedDonorIds, true);

        return match ($level) {
            self::LEVEL_CLOSEST => $all
                ->take(5)
                ->reject($isAlerted)
                ->values(),

            self::LEVEL_WIDER_RADIUS => $all
                ->slice(5, 10)
                ->reject($isAlerted)
                ->values(),

            default => $all
                ->slice(15)
                ->reject($isAlerted)
                ->values(),
        };
    }

    /**
     * @return Collection<int, array{donor: \App\Models\Donor, distance_km: float|null}>
     */
    private function allCompatibleCandidates(BloodRequest $bloodRequest): Collection
    {
        return $this->donorFilterService
            ->filterForRequest(
                requestedBloodType: $bloodRequest->blood_type,
                requestLatitude: $bloodRequest->latitude !== null ? (float) $bloodRequest->latitude : null,
                requestLongitude: $bloodRequest->longitude !== null ? (float) $bloodRequest->longitude : null,
                distanceLimitKm: self::MAX_FILTER_RADIUS_KM,
                requestCity: $bloodRequest->city,
            )
            ->sort(function (array $a, array $b) {
                $aDistance = $a['distance_km'] ?? INF;
                $bDistance = $b['distance_km'] ?? INF;

                return $aDistance <=> $bDistance;
            })
            ->values();
    }
}
