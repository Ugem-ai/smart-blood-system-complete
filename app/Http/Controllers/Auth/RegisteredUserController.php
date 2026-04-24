<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Donor;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('app');
    }

    /**
     * Display the hospital registration view.
     */
    public function createHospital(): View
    {
        return view('app');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        return $this->storeWithRole($request, 'donor');
    }

    /**
     * Handle a hospital registration request.
     */
    public function storeHospital(Request $request): RedirectResponse
    {
        return $this->storeWithRole($request, 'hospital');
    }

    /**
     * Persist a user with a fixed role for the selected registration flow.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function storeWithRole(Request $request, string $role): RedirectResponse
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];

        if ($role === 'donor') {
            $rules = array_merge($rules, [
                'blood_type' => ['required', 'string', 'max:5'],
                'city' => ['required', 'string', 'max:255'],
                'contact_number' => ['required', 'string', 'max:30'],
                'last_donation_date' => ['nullable', 'date'],
                'privacy_consent' => ['accepted'],
            ]);
        }

        $request->validate($rules);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $role,
        ]);

        if ($role === 'donor') {
            Donor::create([
                'user_id' => $user->id,
                'name' => $request->name,
                'blood_type' => $request->blood_type,
                'city' => $request->city,
                'contact_number' => $request->contact_number,
                'email' => $request->email,
                'password' => $request->password,
                'last_donation_date' => $request->last_donation_date,
                'availability' => true,
                'privacy_consent_at' => now(),
            ]);
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect($this->dashboardPathForRole($role));
    }

    protected function dashboardPathForRole(string $role): string
    {
        return match ($role) {
            'hospital' => '/hospital/dashboard',
            'admin' => '/admin/dashboard',
            default => '/donor/dashboard',
        };
    }
}
