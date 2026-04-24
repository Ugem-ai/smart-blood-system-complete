<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminPanelController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\DonorProfileController;
use App\Http\Controllers\Api\DonorResponseController;
use App\Http\Controllers\Api\HospitalInventoryController;
use App\Http\Controllers\Api\HospitalProfileController;
use App\Http\Controllers\Api\HospitalRequestController;
use App\Http\Controllers\Api\MonitoringController;
use Illuminate\Support\Facades\Route;

// Root-level shortcuts for Vue app
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::patch('/me', [AuthController::class, 'updateMe']);
    Route::post('/device-tokens/register', [DeviceTokenController::class, 'store']);
});

Route::prefix('v1')->group(function () {
    Route::get('/monitor/metrics', [MonitoringController::class, 'metrics']);
    Route::get('/monitor/health', [MonitoringController::class, 'health']);

    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });

    Route::middleware(['auth:sanctum', 'audit', 'monitor', 'throttle:60,1'])->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::get('/admin/ping', function () {
            return response()->json(['message' => 'Admin access granted']);
        })->middleware('role:admin');

        Route::get('/donor/ping', function () {
            return response()->json(['message' => 'Donor access granted']);
        })->middleware('role:donor');

        Route::get('/hospital/ping', function () {
            return response()->json(['message' => 'Hospital access granted']);
        })->middleware('role:hospital');
    });
});

Route::middleware(['auth:sanctum', 'role:donor', 'audit', 'monitor', 'throttle:60,1'])->group(function () {
    Route::get('/donor/dashboard', [DonorProfileController::class, 'dashboard']);
    Route::get('/donor/profile', [DonorProfileController::class, 'profile']);
    Route::get('/donor/settings', [DonorProfileController::class, 'settings']);
    Route::patch('/donor/settings', [DonorProfileController::class, 'updateSettings']);
    Route::put('/donor/update', [DonorProfileController::class, 'update']);
    Route::post('/donor/status', [DonorProfileController::class, 'status']);
    Route::post('/donor/accept', [DonorResponseController::class, 'accept']);
    Route::post('/donor/decline', [DonorResponseController::class, 'decline']);
});

Route::middleware('throttle:10,1')->post('/hospital/register', [HospitalProfileController::class, 'register']);

Route::middleware(['auth:sanctum', 'role:hospital', 'audit', 'monitor', 'throttle:60,1'])->group(function () {
    Route::get('/hospital/profile', [HospitalProfileController::class, 'profile']);
    Route::get('/hospital/activity-log', [HospitalProfileController::class, 'activityLog']);
    Route::get('/hospital/settings-snapshot', [HospitalProfileController::class, 'settingsSnapshot']);
    Route::get('/hospital/inventory', [HospitalInventoryController::class, 'index']);
    Route::put('/hospital/inventory', [HospitalInventoryController::class, 'update']);

    // Canonical REST-style hospital request routes
    Route::post('/hospital/requests', [HospitalRequestController::class, 'store']);
    Route::get('/hospital/requests', [HospitalRequestController::class, 'index']);
    Route::get('/hospital/requests/{bloodRequest}', [HospitalRequestController::class, 'show']);
    Route::match(['put', 'patch'], '/hospital/requests/{bloodRequest}', [HospitalRequestController::class, 'update']);
    Route::get('/hospital/requests/{bloodRequest}/matched-donors', [HospitalRequestController::class, 'matchedDonors']);

    // Backward-compatible aliases
    Route::post('/hospital/request', [HospitalRequestController::class, 'store']);
    Route::get('/hospital/request/list', [HospitalRequestController::class, 'list']);
    Route::get('/hospital/request/{bloodRequest}', [HospitalRequestController::class, 'show']);
    Route::get('/hospital/request/{bloodRequest}/matched-donors', [HospitalRequestController::class, 'matchedDonors']);

    Route::post('/hospital/confirm-donation', [HospitalRequestController::class, 'confirmDonation']);
});

Route::middleware(['auth:sanctum', 'role:admin', 'audit', 'monitor', 'throttle:60,1'])->group(function () {
    Route::get('/admin/dashboard', [AdminPanelController::class, 'dashboard']);
    Route::get('/admin/emergency-dashboard/live', [AdminPanelController::class, 'emergencyDashboardLive']);
    Route::get('/admin/national-integrations/partners', [AdminPanelController::class, 'nationalIntegrationPartners']);
    Route::post('/admin/national-integrations/{partner}/sync-emergency', [AdminPanelController::class, 'syncNationalIntegrationEmergency']);
    Route::get('/admin/national-integrations/logs', [AdminPanelController::class, 'nationalIntegrationLogs']);
    Route::get('/admin/emergency-mode', [AdminPanelController::class, 'emergencyModeStatus']);
    Route::patch('/admin/emergency-mode', [AdminPanelController::class, 'setEmergencyMode']);
    Route::get('/admin/settings', [AdminPanelController::class, 'settings']);
    Route::patch('/admin/settings', [AdminPanelController::class, 'updateSettings']);
    Route::patch('/admin/hospitals/{hospital}/approve', [AdminPanelController::class, 'approveHospital']);
    Route::patch('/admin/hospitals/{hospital}/reject', [AdminPanelController::class, 'rejectHospital']);
    Route::get('/admin/hospitals', [AdminPanelController::class, 'hospitals']);
    Route::get('/admin/hospitals/{hospital}', [AdminPanelController::class, 'hospitalProfile']);
    Route::get('/admin/hospitals/{hospital}/requests', [AdminPanelController::class, 'hospitalRequests']);
    Route::post('/admin/hospitals/{hospital}/alert', [AdminPanelController::class, 'alertHospital']);
    Route::post('/admin/hospitals/{hospital}/toggle-status', [AdminPanelController::class, 'toggleHospitalStatus']);
    Route::get('/hospitals', [AdminPanelController::class, 'hospitals']);
    Route::get('/hospitals/{hospital}', [AdminPanelController::class, 'hospitalProfile']);
    Route::get('/hospitals/{hospital}/requests', [AdminPanelController::class, 'hospitalRequests']);
    Route::post('/hospitals/{hospital}/alert', [AdminPanelController::class, 'alertHospital']);
    Route::post('/hospitals/{hospital}/toggle-status', [AdminPanelController::class, 'toggleHospitalStatus']);
    Route::get('/admin/hospital-invites', [AdminPanelController::class, 'hospitalInviteCodes']);
    Route::post('/admin/hospital-invites', [AdminPanelController::class, 'createHospitalInviteCode']);
    Route::patch('/admin/hospital-invites/{hospitalInviteCode}/revoke', [AdminPanelController::class, 'revokeHospitalInviteCode']);
    Route::get('/admin/requests', [AdminPanelController::class, 'bloodRequests']);
    Route::get('/admin/notifications/requests', [AdminPanelController::class, 'notificationRequestOptions']);
    Route::get('/admin/notifications/{bloodRequest}', [AdminPanelController::class, 'notificationDashboard']);
    Route::post('/admin/notifications/{bloodRequest}/control', [AdminPanelController::class, 'notificationControl']);
    Route::get('/admin/past-match/requests', [AdminPanelController::class, 'pastMatchRequestOptions']);
    Route::get('/admin/past-match/{bloodRequest}', [AdminPanelController::class, 'pastMatchDetails']);
    Route::post('/admin/past-match/{bloodRequest}/control', [AdminPanelController::class, 'pastMatchControl']);
    Route::get('/past-match/requests', [AdminPanelController::class, 'pastMatchRequestOptions']);
    Route::get('/past-match/{bloodRequest}', [AdminPanelController::class, 'pastMatchDetails']);
    Route::post('/past-match/{bloodRequest}/control', [AdminPanelController::class, 'pastMatchControl']);
    Route::get('/admin/requests/{bloodRequest}/matched-donors', [AdminPanelController::class, 'requestMatchedDonors']);
    Route::post('/admin/requests/{bloodRequest}/trigger-matching', [AdminPanelController::class, 'triggerMatching']);
    Route::patch('/admin/requests/{bloodRequest}', [AdminPanelController::class, 'updateRequest']);
    Route::get('/admin/donors/active', [AdminPanelController::class, 'activeDonors']);
    Route::get('/admin/donors', [AdminPanelController::class, 'donors']);
    Route::get('/admin/donors/{donor}', [AdminPanelController::class, 'donorProfile']);
    Route::post('/admin/donors/{donor}/notify', [AdminPanelController::class, 'notifyDonor']);
    Route::post('/admin/donors/{donor}/suspend', [AdminPanelController::class, 'suspendDonor']);
    Route::post('/admin/donors/{donor}/prioritize', [AdminPanelController::class, 'prioritizeDonor']);
    Route::patch('/admin/donors/{donor}', [AdminPanelController::class, 'updateDonorStatus']);
    Route::get('/donors', [AdminPanelController::class, 'donors']);
    Route::get('/donors/{donor}', [AdminPanelController::class, 'donorProfile']);
    Route::post('/donors/{donor}/notify', [AdminPanelController::class, 'notifyDonor']);
    Route::post('/donors/{donor}/suspend', [AdminPanelController::class, 'suspendDonor']);
    Route::post('/donors/{donor}/prioritize', [AdminPanelController::class, 'prioritizeDonor']);
    Route::get('/admin/analytics', [AdminPanelController::class, 'analytics']);
    Route::get('/admin/logs', [AdminPanelController::class, 'auditLogs']);
    Route::get('/admin/users', [AdminPanelController::class, 'users']);
    Route::patch('/admin/users/{user}', [AdminPanelController::class, 'updateUser']);
    Route::delete('/admin/users/{user}', [AdminPanelController::class, 'deleteUser']);
});
