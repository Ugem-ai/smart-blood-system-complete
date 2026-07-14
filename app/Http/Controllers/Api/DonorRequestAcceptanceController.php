<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BloodRequest;
use App\Models\DonorRequestAcceptance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DonorRequestAcceptanceController extends Controller
{
    public function store(Request $request, BloodRequest $bloodRequest): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:accepted,declined'],
        ]);

        $donor = $request->user()?->donorProfile;

        if (! $donor) {
            return response()->json([
                'success' => false,
                'message' => 'Donor profile not found.',
                'data' => null,
            ], 404);
        }

        if (! (bool) $bloodRequest->is_emergency) {
            return response()->json([
                'success' => false,
                'message' => 'Travel acceptance is only available for emergency requests.',
                'data' => null,
            ], 422);
        }

        if ($bloodRequest->expiry_time !== null && now()->greaterThan($bloodRequest->expiry_time)) {
            DonorRequestAcceptance::expirePendingForRequest($bloodRequest);

            return response()->json([
                'success' => false,
                'message' => 'This emergency request has expired.',
                'data' => null,
            ], 422);
        }

        $acceptance = DonorRequestAcceptance::query()
            ->where('donor_id', $donor->id)
            ->where('blood_request_id', $bloodRequest->id)
            ->first();

        if (! $acceptance) {
            return response()->json([
                'success' => false,
                'message' => 'No travel acceptance invitation found for this request.',
                'data' => null,
            ], 403);
        }

        if ($acceptance->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Travel acceptance response already recorded for this request.',
                'data' => [
                    'blood_request_id' => $bloodRequest->id,
                    'donor_id' => $donor->id,
                    'status' => $acceptance->status,
                    'accepted_at' => $acceptance->accepted_at,
                ],
            ], 409);
        }

        $distanceAtAcceptance = $this->distanceKm($donor->latitude, $donor->longitude, $bloodRequest->latitude, $bloodRequest->longitude);

        $acceptance->update([
            'distance_km_at_acceptance' => $distanceAtAcceptance,
            'status' => $validated['status'],
            'accepted_at' => $validated['status'] === 'accepted' ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Travel acceptance response recorded.',
            'data' => [
                'blood_request_id' => $bloodRequest->id,
                'donor_id' => $donor->id,
                'status' => $acceptance->status,
                'distance_km_at_acceptance' => $acceptance->distance_km_at_acceptance,
                'accepted_at' => $acceptance->accepted_at,
            ],
        ]);
    }

    private function distanceKm(?float $donorLatitude, ?float $donorLongitude, ?float $requestLatitude, ?float $requestLongitude): ?float
    {
        if ($donorLatitude === null || $donorLongitude === null || $requestLatitude === null || $requestLongitude === null) {
            return null;
        }

        $earthRadiusKm = 6371;

        $dLat = deg2rad($requestLatitude - $donorLatitude);
        $dLon = deg2rad($requestLongitude - $donorLongitude);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($donorLatitude)) * cos(deg2rad($requestLatitude))
            * sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadiusKm * $c, 2);
    }
}
