<?php

namespace App\Http\Requests;

use App\Models\VolunteerProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Public registration is open to anyone.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for volunteer sign-up.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'contact_number' => ['required', 'string', 'max:50'],
            'address_area' => ['required', 'string', 'max:255'],
            'birthdate' => ['required', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString()],
            'is_resident' => ['required', 'boolean', 'accepted'],
            'availability' => ['sometimes', 'string', Rule::in(VolunteerProfile::AVAILABILITIES)],
            'training' => ['nullable', 'string', 'max:1000'],
            'skills' => ['sometimes', 'array'],
            'skills.*' => ['integer', 'exists:skills,id'],
            'consent' => ['required', 'boolean', 'accepted'],
            'policy_version' => ['sometimes', 'string', 'max:50'],
        ];
    }

    /**
     * Custom messages matching docs/api.md.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'birthdate.before_or_equal' => 'You must be 18 or older.',
            'is_resident.accepted' => 'You must be a resident of Barangay Santolan.',
            'consent.accepted' => 'You must accept the privacy notice.',
            'skills.*.exists' => 'The selected skill is invalid.',
        ];
    }
}
