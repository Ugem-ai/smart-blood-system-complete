<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBloodRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Actual authorisation is enforced by the auth:sanctum + role:hospital middleware stack.
        return true;
    }

    /**
     * Normalise legacy / alternate field names before validation fires so the
     * validator always sees the canonical schema.
     */
    protected function prepareForValidation(): void
    {
        $input = $this->all();

        // Accept multiple legacy unit-count keys
        $units = $input['units_required']
            ?? $input['units_needed']
            ?? $input['requested_units']
            ?? $input['quantity']
            ?? null;

        // Normalise urgency level
        $urgency = $input['urgency_level'] ?? $input['urgency'] ?? null;
        if ($urgency !== null) {
            $urgency = strtolower(trim((string) $urgency));
            if ($urgency === 'normal') {
                $urgency = 'low';
            }
        }

        $this->merge([
            'blood_type'       => isset($input['blood_type']) ? strtoupper((string) $input['blood_type']) : null,
            'units_required'   => $units,
            'urgency_level'    => $urgency,
            'city'             => $input['city'] ?? $input['location'] ?? null,
            'distance_limit_km'=> $input['distance_limit_km'] ?? $input['distance_radius_km'] ?? null,
            'required_on'      => $input['required_on'] ?? $input['required_date'] ?? null,
        ]);
    }

    public function rules(): array
    {
        return [
            // ── Patient / Case context ─────────────────────────────────────────
            'blood_type'        => ['required', 'string', 'in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
            'component'         => ['nullable', 'string', 'in:Whole Blood,PRBC,Platelets,Plasma'],
            'units_required'    => ['required', 'integer', 'min:1', 'max:20'],
            'urgency_level'     => ['required', 'string', 'in:low,medium,high,critical'],
            'reason'            => ['nullable', 'string', 'max:100'],

            // ── Hospital contact override ──────────────────────────────────────
            'contact_person'    => ['nullable', 'string', 'max:150'],
            'contact_number'    => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{7,20}$/'],

            // ── Location ──────────────────────────────────────────────────────
            'city'              => ['required', 'string', 'max:255'],
            'province'          => ['nullable', 'string', 'max:100'],
            'latitude'          => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'         => ['nullable', 'numeric', 'between:-180,180'],
            'distance_limit_km' => ['required', 'numeric', 'min:1', 'max:500'],

            // ── Time constraints ──────────────────────────────────────────────
            'required_on'       => ['nullable', 'date'],
            'expiry_time'       => ['nullable', 'date', 'after_or_equal:required_on'],

            // ── System control override ───────────────────────────────────────
            'is_emergency'      => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'blood_type.in'         => 'Blood type must be one of: A+, A-, B+, B-, AB+, AB-, O+, O-.',
            'component.in'          => 'Component must be one of: Whole Blood, PRBC, Platelets, Plasma.',
            'urgency_level.in'      => 'Urgency level must be: low, medium, high, or critical.',
            'units_required.min'    => 'At least 1 unit is required.',
            'units_required.max'    => 'Maximum of 20 units per request.',
            'distance_limit_km.required' => 'A search radius (distance_limit_km) is required.',
            'distance_limit_km.min' => 'Search radius must be at least 1 km.',
            'distance_limit_km.max' => 'Search radius cannot exceed 500 km.',
            'contact_number.regex'  => 'Contact number must contain only digits, spaces, +, -, or parentheses (7–20 chars).',
            'expiry_time.after_or_equal' => 'Expiry time must be on or after the required date.',
        ];
    }
}
