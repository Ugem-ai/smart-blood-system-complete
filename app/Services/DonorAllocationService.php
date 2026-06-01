<?php

namespace App\Services;

use App\Models\BloodRequest;
use App\Models\RequestMatch;

class DonorAllocationService
{
    private const URGENCY_PRIORITY_MAP = [
        'low' => 1,
        'medium' => 2,
        'high' => 3,
        'critical' => 4,
    ];

    /**
     * @return array<int, string>
     */
    public function activeRequestStatuses(): array
    {
        return ['pending', 'matching', 'matched', 'confirmed', 'open'];
    }

    protected function urgencyPriority(string $urgencyLevel): int
    {
        return self::URGENCY_PRIORITY_MAP[strtolower(trim($urgencyLevel))] ?? 2;
    }

    /**
     * @return array<int, string>
     */
    protected function urgencyLevelsAtOrAbove(string $urgencyLevel): array
    {
        $minimumPriority = $this->urgencyPriority($urgencyLevel);

        return array_keys(array_filter(self::URGENCY_PRIORITY_MAP, fn (int $priority) => $priority >= $minimumPriority));
    }

    protected function pendingReservationExpirationMinutes(): int
    {
        return max(0, (int) config('services.notifications.pending_reservation_expiration_minutes', 10));
    }

    public function activeAllocationForDonor(int $donorId, ?int $excludingRequestId = null): ?RequestMatch
    {
        return RequestMatch::query()
            ->where('donor_id', $donorId)
            ->where('response_status', 'accepted')
            ->whereHas('bloodRequest', function ($query) use ($excludingRequestId) {
                $query->whereIn('status', $this->activeRequestStatuses());

                if ($excludingRequestId !== null) {
                    $query->where('id', '!=', $excludingRequestId);
                }
            })
            ->with('bloodRequest')
            ->first();
    }

    public function donorHasActiveAllocation(int $donorId, ?int $excludingRequestId = null): bool
    {
        return $this->activeAllocationForDonor($donorId, $excludingRequestId) !== null;
    }

    /**
     * @return array<int, int>
     */
    public function reservedDonorIds(?int $excludingRequestId = null, ?string $requestUrgencyLevel = null, bool $requestIsEmergency = false): array
    {
        $acceptedQuery = RequestMatch::query()
            ->where('response_status', 'accepted')
            ->whereHas('bloodRequest', function ($query) use ($excludingRequestId) {
                $query->whereIn('status', $this->activeRequestStatuses());

                if ($excludingRequestId !== null) {
                    $query->where('id', '!=', $excludingRequestId);
                }
            });

        $reservedIds = $acceptedQuery->pluck('donor_id')->map(fn ($id) => (int) $id)->all();

        $usesPendingReservation = $requestIsEmergency || strtolower((string) $requestUrgencyLevel) === 'critical';
        if ($usesPendingReservation) {
            $pendingQuery = RequestMatch::query()
                ->where('response_status', 'pending')
                ->whereHas('bloodRequest', function ($query) use ($excludingRequestId) {
                    $query->whereIn('status', $this->activeRequestStatuses());

                    if ($excludingRequestId !== null) {
                        $query->where('id', '!=', $excludingRequestId);
                    }

                    $query->where(function ($priorityQuery) {
                        $priorityQuery->where('is_emergency', true)
                            ->orWhere('urgency_level', 'critical');
                    });
                });

            $expirationMinutes = $this->pendingReservationExpirationMinutes();
            if ($expirationMinutes > 0) {
                $cutoff = now()->subMinutes($expirationMinutes);
                $pendingQuery->where('created_at', '>=', $cutoff);
            }

            $reservedIds = array_merge($reservedIds, $pendingQuery->pluck('donor_id')->map(fn ($id) => (int) $id)->all());
        }

        return array_values(array_unique($reservedIds));
    }

    public function reserveDonorForRequest(int $donorId, BloodRequest $bloodRequest): void
    {
        RequestMatch::query()
            ->where('donor_id', $donorId)
            ->where(function ($query) use ($bloodRequest) {
                $query->where('request_id', $bloodRequest->id)
                    ->orWhere('blood_request_id', $bloodRequest->id);
            })
            ->update([
                'response_status' => 'accepted',
            ]);

        $this->expireCompetingMatches($donorId, $bloodRequest->id);
    }

    public function expireCompetingMatches(int $donorId, int $acceptedRequestId): int
    {
        return RequestMatch::query()
            ->where('donor_id', $donorId)
            ->where('response_status', 'pending')
            ->where(function ($query) use ($acceptedRequestId) {
                $query->where('request_id', '!=', $acceptedRequestId)
                    ->orWhereNull('request_id');
            })
            ->whereHas('bloodRequest', function ($query) use ($acceptedRequestId) {
                $query->where('id', '!=', $acceptedRequestId)
                    ->whereIn('status', $this->activeRequestStatuses());
            })
            ->update([
                'response_status' => 'expired',
            ]);
    }

    /**
     * @return array{coordination_status: string, allocated_request_id: int|null}
     */
    public function coordinationStateForDonorOnRequest(int $donorId, int $requestId): array
    {
        $active = $this->activeAllocationForDonor($donorId);

        if (! $active) {
            return [
                'coordination_status' => 'available',
                'allocated_request_id' => null,
            ];
        }

        $activeRequestId = (int) ($active->blood_request_id ?: $active->request_id);

        if ($activeRequestId === $requestId) {
            return [
                'coordination_status' => 'reserved_here',
                'allocated_request_id' => $activeRequestId,
            ];
        }

        return [
            'coordination_status' => 'reserved_elsewhere',
            'allocated_request_id' => $activeRequestId,
        ];
    }
}
