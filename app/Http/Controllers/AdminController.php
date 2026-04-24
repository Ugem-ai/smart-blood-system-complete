<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\BloodRequest;
use App\Models\DonationHistory;
use App\Models\Donor;
use App\Models\Hospital;
use App\Services\EmergencyBroadcastModeService;
use App\Services\EmergencyDashboardService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function dashboard(
        EmergencyDashboardService $emergencyDashboardService,
        EmergencyBroadcastModeService $emergencyBroadcastModeService
    ): View {
        $stats = [
            'donations'     => DonationHistory::query()->count(),
            'requests'      => BloodRequest::query()->count(),
            'active_donors' => Donor::query()->where('availability', true)->count(),
        ];

        $donors       = Donor::query()->latest()->get();
        $bloodRequests = BloodRequest::query()->latest()->get();
        $activityLogs  = ActivityLog::query()->latest()->take(50)->get();

        $emergencyDashboard  = $emergencyDashboardService->snapshot();
        $emergencyMode       = $emergencyBroadcastModeService->state();
        $disasterResponseMode = $emergencyBroadcastModeService->disasterResponseState();

        return view('app', compact(
            'stats',
            'donors',
            'bloodRequests',
            'activityLogs',
            'emergencyDashboard',
            'emergencyMode',
            'disasterResponseMode',
        ));
    }

    public function emergencyDashboardLive(EmergencyDashboardService $emergencyDashboardService): JsonResponse
    {
        return response()->json([
            'data' => $emergencyDashboardService->snapshot(),
        ]);
    }

    public function setEmergencyMode(Request $request, EmergencyBroadcastModeService $emergencyBroadcastModeService): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'trigger' => ['nullable', 'string', 'max:255'],
            'expires_in_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
        ]);

        if ((bool) $validated['enabled'] && blank($validated['trigger'] ?? null)) {
            return back()->withErrors([
                'trigger' => 'A trigger is required when activating emergency mode.',
            ]);
        }

        if ((bool) $validated['enabled']) {
            $emergencyBroadcastModeService->activate(
                $validated['trigger'] ?? null,
                $request->user()?->id,
                isset($validated['expires_in_hours']) ? (int) $validated['expires_in_hours'] : null
            );

            return back()->with('status', 'emergency-mode-activated');
        }

        $emergencyBroadcastModeService->deactivate($request->user()?->id);

        return back()->with('status', 'emergency-mode-deactivated');
    }

    public function approveHospital(Request $request, Hospital $hospital): RedirectResponse
    {
        $hospital->update(['status' => 'approved']);

        ActivityLog::record($request->user()?->id, 'hospital.approved', [
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
        ]);

        return back()->with('status', 'hospital-approved');
    }

    public function rejectHospital(Request $request, Hospital $hospital): RedirectResponse
    {
        $hospital->update(['status' => 'rejected']);

        ActivityLog::record($request->user()?->id, 'hospital.rejected', [
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
        ]);

        return back()->with('status', 'hospital-rejected');
    }

    public function donors(): View
    {
        return view('app');
    }

    public function bloodRequests(): View
    {
        return view('app');
    }

}
