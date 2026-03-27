<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Donor;
use App\Models\Hospital;
use App\Models\User;
use App\Services\HospitalInviteCodeService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    public function register(Request $request, HospitalInviteCodeService $inviteCodes): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
            'role' => ['required', 'in:donor,hospital'],
            'blood_type' => ['required_if:role,donor', 'nullable', 'string', 'max:5'],
            'city' => ['required_if:role,donor', 'nullable', 'string', 'max:255'],
            'contact_number' => ['required_if:role,donor,hospital', 'nullable', 'string', 'max:30'],
            'privacy_consent' => ['nullable'],
            'hospital_name' => ['required_if:role,hospital', 'nullable', 'string', 'max:255'],
            'address' => ['required_if:role,hospital', 'nullable', 'string', 'max:255'],
            'hospital_registration_code' => ['nullable', 'string'],
            'invite_code' => ['nullable', 'string'],
        ]);

        if ($validated['role'] === 'hospital') {
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
        }

        if ($validated['role'] === 'donor' && empty($validated['privacy_consent'])) {
            return response()->json([
                'message' => 'The privacy consent field must be accepted.',
                'errors' => [
                    'privacy_consent' => ['The privacy consent field must be accepted.'],
                ],
            ], 422);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        if ($validated['role'] === 'donor') {
            Donor::create([
                'user_id' => $user->id,
                'name' => $validated['name'],
                'blood_type' => $validated['blood_type'],
                'city' => $validated['city'],
                'contact_number' => $validated['contact_number'],
                'phone' => $validated['contact_number'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'availability' => true,
                'reliability_score' => 0,
                'privacy_consent_at' => ! empty($validated['privacy_consent']) ? now() : null,
            ]);
        }

        if ($validated['role'] === 'hospital') {
            Hospital::create([
                'user_id' => $user->id,
                'hospital_name' => $validated['hospital_name'],
                'address' => $validated['address'],
                'location' => $validated['address'],
                'contact_person' => $validated['name'],
                'contact_number' => $validated['contact_number'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'status' => 'pending',
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        if ($user->role === 'hospital' && $user->hospitalProfile?->status !== 'approved') {
            return response()->json(['message' => 'Hospital account is pending admin approval or has been rejected.'], 403);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logout successful.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()]);
    }

    public function updateMe(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user,
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = PasswordBroker::sendResetLink([
            'email' => $validated['email'],
        ]);

        if ($status !== PasswordBroker::RESET_LINK_SENT) {
            return response()->json([
                'message' => __($status),
            ], 422);
        }

        return response()->json([
            'message' => __($status),
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = PasswordBroker::reset(
            [
                'email' => $validated['email'],
                'password' => $validated['password'],
                'password_confirmation' => $validated['password_confirmation'],
                'token' => $validated['token'],
            ],
            function (User $user) use ($validated): void {
                $user->forceFill([
                    'password' => Hash::make($validated['password']),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            return response()->json([
                'message' => __($status),
            ], 422);
        }

        return response()->json([
            'message' => __($status),
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
