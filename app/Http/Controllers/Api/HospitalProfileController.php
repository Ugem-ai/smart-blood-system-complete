<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Hospital;
use App\Models\User;
use App\Services\HospitalInviteCodeService;
use App\Services\InventoryMonitoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class HospitalProfileController extends Controller
{
    public function register(Request $request, HospitalInviteCodeService $inviteCodes): JsonResponse
    {
        $validated = $request->validate([
            'hospital_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'contact_person' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'hospital_registration_code' => ['nullable', 'string'],
            'invite_code' => ['nullable', 'string'],
        ]);

        if (! $this->isAllowedHospitalDomain($validated['email'])) {
            return response()->json([
                'message' => 'Hospital registration is restricted to approved institutional email domains.',
            ], 403);
        }

        $inviteCode = trim((string) ($validated['invite_code'] ?? ''));
        $registrationCode = $validated['hospital_registration_code'] ?? null;

        if ($inviteCode === '' && ! is_string($registrationCode)) {
            return response()->json([
                'message' => 'Either invite_code or hospital_registration_code is required for hospital registration.',
            ], 422);
        }

        if ($inviteCode !== '') {
            if (! $inviteCodes->validateAndConsume($inviteCode, $validated['email'])) {
                return response()->json([
                    'message' => 'Invalid, expired, revoked, or already-used hospital invite code.',
                ], 403);
            }
        } elseif (! $this->isValidHospitalRegistrationCode($registrationCode)) {
            return response()->json([
                'message' => 'Invalid hospital registration code.',
            ], 403);
        }

        $user = User::create([
            'name' => $validated['contact_person'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'hospital',
        ]);

        $hospital = Hospital::create([
            'user_id' => $user->id,
            'hospital_name' => $validated['hospital_name'],
            'address' => $validated['address'],
            'location' => $validated['address'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'contact_person' => $validated['contact_person'],
            'contact_number' => $validated['contact_number'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'status' => 'pending',
        ]);

        ActivityLog::record($user->id, 'hospital.registered', [
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Hospital registration submitted. Await admin approval.',
            'data' => [
                'user_id' => $user->id,
                'hospital_id' => $hospital->id,
                'hospital_name' => $hospital->hospital_name,
                'status' => $hospital->status,
            ],
        ], 201);
    }

    public function profile(Request $request, InventoryMonitoringService $inventoryService): JsonResponse
    {
        $hospital = $request->user()->hospitalProfile;

        if (! $hospital) {
            return response()->json(['message' => 'Hospital profile not found.'], 404);
        }

        $bloodRequests = $hospital->bloodRequests()->latest()->take(10)->get();

        return response()->json([
            'data' => [
                'id' => $hospital->id,
                'user_id' => $hospital->user_id,
                'hospital_name' => $hospital->hospital_name,
                'address' => $hospital->address ?? $hospital->location,
                'latitude' => $hospital->latitude,
                'longitude' => $hospital->longitude,
                'contact_person' => $hospital->contact_person,
                'contact_number' => $hospital->contact_number,
                'email' => $hospital->email,
                'status' => $hospital->status,
                'inventory' => $hospital->bloodInventories()->orderBy('blood_type')->get(),
                'low_stock_alerts' => $inventoryService->lowStockAlerts($hospital),
                'dashboard' => [
                    'total_requests' => $hospital->bloodRequests()->count(),
                    'pending_requests' => $hospital->bloodRequests()->where('status', 'pending')->count(),
                    'matching_requests' => $hospital->bloodRequests()->where('status', 'matching')->count(),
                    'completed_requests' => $hospital->bloodRequests()->where('status', 'completed')->count(),
                    'recent_requests' => $bloodRequests,
                ],
            ],
        ]);
    }

    private function isAllowedHospitalDomain(string $email): bool
    {
        $allowedDomains = collect(explode(',', (string) env('HOSPITAL_EMAIL_DOMAINS', '')))
            ->map(fn ($domain) => Str::lower(trim($domain)))
            ->filter()
            ->values()
            ->all();

        if (empty($allowedDomains)) {
            return true;
        }

        $emailDomain = Str::lower(Str::after((string) $email, '@'));

        return in_array($emailDomain, $allowedDomains, true);
    }

    private function isValidHospitalRegistrationCode(?string $code): bool
    {
        $expectedCode = (string) env('HOSPITAL_REGISTRATION_CODE', '');

        if ($expectedCode === '') {
            return false;
        }

        if (! is_string($code) || $code === '') {
            return false;
        }

        return hash_equals($expectedCode, $code);
    }
}
