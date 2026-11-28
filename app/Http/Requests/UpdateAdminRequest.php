<?php

namespace App\Http\Requests;

use App\Models\AdminPermission;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminRequest extends FormRequest
{
    /**
     * Route middleware enforces superadmin-only access.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for updating an admin account.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', 'string', Rule::in([User::ROLE_ADMIN, User::ROLE_SUPERADMIN])],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(AdminPermission::ALL)],
            'is_active' => ['sometimes', 'boolean'],
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
            'permissions.*.in' => 'The selected permission is invalid.',
        ];
    }
}
