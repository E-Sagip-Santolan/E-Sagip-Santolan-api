<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Models\VolunteerProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListVolunteersRequest extends FormRequest
{
    /**
     * Route middleware enforces admin/superadmin permission checks.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for volunteer list query parameters.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::in([
                User::STATUS_PENDING,
                User::STATUS_APPROVED,
                User::STATUS_REJECTED,
            ])],
            'skill' => ['sometimes', 'integer', 'exists:skills,id'],
            'availability' => ['sometimes', 'string', Rule::in(VolunteerProfile::AVAILABILITIES)],
            'address_area' => ['sometimes', 'string', 'max:255'],
            'search' => ['sometimes', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }
}
