<?php

namespace App\Http\Controllers;

use App\Models\BloodRequest;
use App\Models\DonorRequestResponse;
use App\Services\DonorAchievementService;
use App\Services\DonorRouteAssistanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DonorController extends Controller
{
    public function dashboard(
        Request $request,
        DonorRouteAssistanceService $routeAssistanceService,
        DonorAchievementService $achievementService
    ): View {
        $user  = $request->user();
        $donor = $user->donorProfile;

        $routeAssistanceData = null;
        $achievements        = [];
        $engagementSummary   = [];

        if ($donor) {
            $nearestRequest = BloodRequest::query()
                ->where('blood_type', $donor->blood_type)
                ->whereIn('status', ['matching', 'pending'])
                ->latest()
                ->first();

            if ($nearestRequest) {
                $routeAssistanceData            = $routeAssistanceService->assistanceForRequest(
                    $donor->latitude  ?? null,
                    $donor->longitude ?? null,
                    $nearestRequest->latitude  ?? null,
                    $nearestRequest->longitude ?? null,
                    $donor->city,
                    $nearestRequest->city,
                    $nearestRequest->hospital_name,
                );
                $routeAssistanceData['request'] = $nearestRequest;
            }

            $achievements      = $achievementService->achievementsForDonor($donor);
            $engagementSummary = $achievementService->engagementSummary($donor);
        }

        return view('dashboards.donor', compact(
            'donor',
            'routeAssistanceData',
            'achievements',
            'engagementSummary',
        ));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'blood_type' => ['required', 'string', 'max:5'],
            'city' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:30'],
            'last_donation_date' => ['nullable', 'date'],
        ]);

        $user = $request->user();
        $user->update([
            'name' => $validated['name'],
        ]);

        $this->resolveDonorProfile($request);
        $user->donorProfile()->update($validated);

        return back()->with('status', 'donor-profile-updated');
    }

    public function updateAvailability(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'availability' => ['required', 'boolean'],
        ]);

        $this->resolveDonorProfile($request);

        $request->user()->donorProfile()->update([
            'availability' => (bool) $validated['availability'],
        ]);

        return back()->with('status', 'donor-availability-updated');
    }

    public function donationHistory(): View
    {
        return view('app');
    }

    public function respondToRequest(Request $request, BloodRequest $bloodRequest): RedirectResponse
    {
        $validated = $request->validate([
            'response' => ['required', 'in:accepted,declined'],
        ]);

        $donor = $this->resolveDonorProfile($request);

        DonorRequestResponse::updateOrCreate(
            [
                'donor_id'         => $donor->id,
                'blood_request_id' => $bloodRequest->id,
            ],
            [
                'response'     => $validated['response'],
                'responded_at' => now(),
            ]
        );

        if ($bloodRequest->status === 'pending') {
            $bloodRequest->update(['status' => 'matching']);
        }

        return back()->with('status', 'donor-response-recorded');
    }

    protected function resolveDonorProfile(Request $request)
    {
        $user = $request->user();

        return $user->donorProfile()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'name' => $user->name,
                'blood_type' => 'UNK',
                'city' => 'Unknown',
                'contact_number' => 'N/A',
                'email' => $user->email,
                'password' => 'password',
                'availability' => true,
            ]
        );
    }
}
