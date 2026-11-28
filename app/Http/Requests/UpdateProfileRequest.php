<?php

namespace App\Http\Requests;

use App\Models\VolunteerProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Any authenticated user may update their own profile.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for partial profile updates.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'contact_number' => ['sometimes', 'string', 'max:50'],
            'address_area' => ['sometimes', 'string', 'max:255'],
            'availability' => ['sometimes', 'string', Rule::in(VolunteerProfile::AVAILABILITIES)],
            'training' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'skills' => ['sometimes', 'array'],
            'skills.*' => ['integer', 'exists:skills,id'],
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
            'skills.*.exists' => 'The selected skill is invalid.',
        ];
    }
}
