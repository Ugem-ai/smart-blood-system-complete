<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\DonorController;
use App\Http\Controllers\HospitalController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'app');

Route::middleware('auth')->group(function () {
    // Generic dashboard redirect based on role
    Route::get('/dashboard', function () {
        $role = (string) (request()->user()?->role ?? 'donor');
        return match ($role) {
            'hospital' => redirect()->route('hospital.dashboard'),
            'admin'    => redirect()->route('admin.dashboard'),
            default    => redirect()->route('donor.dashboard'),
        };
    })->name('dashboard');

    // Admin routes
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');
        Route::get('/admin/emergency-dashboard/live', [AdminController::class, 'emergencyDashboardLive'])->name('admin.emergency-dashboard.live');
        Route::patch('/admin/emergency-mode', [AdminController::class, 'setEmergencyMode'])->name('admin.emergency-mode');
        Route::patch('/admin/hospitals/{hospital}/approve', [AdminController::class, 'approveHospital'])->name('admin.hospitals.approve');
        Route::patch('/admin/hospitals/{hospital}/reject', [AdminController::class, 'rejectHospital'])->name('admin.hospitals.reject');
    });

    // Hospital routes
    Route::middleware('role:hospital')->group(function () {
        Route::get('/hospital/dashboard', [HospitalController::class, 'dashboard'])->name('hospital.dashboard');
        Route::post('/hospital/requests', [HospitalController::class, 'storeRequest'])->name('hospital.requests.submit');
        Route::patch('/hospital/requests/{bloodRequest}', [HospitalController::class, 'updateRequestStatus'])->name('hospital.requests.update-status');
        Route::get('/hospital/requests/{bloodRequest}/matched-donors', [HospitalController::class, 'matchedDonors'])->name('hospital.requests.matched-donors');
    });

    // Donor routes
    Route::middleware('role:donor')->group(function () {
        Route::get('/donor/dashboard', [DonorController::class, 'dashboard'])->name('donor.dashboard');
        Route::patch('/donor/profile', [DonorController::class, 'updateProfile'])->name('donor.profile.update');
        Route::patch('/donor/availability', [DonorController::class, 'updateAvailability'])->name('donor.availability.update');
        Route::patch('/donor/requests/{bloodRequest}', [DonorController::class, 'respondToRequest'])->name('donor.requests.respond');
    });

    // Profile management
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::view('/blood-requests', 'app');
Route::view('/donation-history', 'app');
Route::view('/settings', 'app');

require __DIR__.'/auth.php';

Route::view('/{any}', 'app')
    ->where('any', '^(?!api).*$');
