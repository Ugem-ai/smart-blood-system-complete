<?php

namespace App\Services;

use App\Models\BloodRequest;
use App\Models\RequestMatch;

class DonorAllocationService
{
    /**
     * @return array<int, string>
     */
    public function activeRequestStatuses(): array
    {
        return ['pending', 'matching', 'matched', 'confirmed', 'open'];
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
    public function reservedDonorIds(?int $excludingRequestId = null): array
    {
        return RequestMatch::query()
            ->where('response_status', 'accepted')
            ->whereHas('bloodRequest', function ($query) use ($excludingRequestId) {
                $query->whereIn('status', $this->activeRequestStatuses());

                if ($excludingRequestId !== null) {
                    $query->where('id', '!=', $excludingRequestId);
                }
            })
            ->pluck('donor_id')
            ->map(fn ($id) => (int) $id)
            ->all();
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
