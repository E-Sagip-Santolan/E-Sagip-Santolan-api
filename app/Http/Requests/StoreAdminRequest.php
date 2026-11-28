<?php

namespace App\Http\Requests;

use App\Models\AdminPermission;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreAdminRequest extends FormRequest
{
    /**
     * Route middleware enforces superadmin-only access.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for creating an admin account.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', 'string', Rule::in([User::ROLE_ADMIN, User::ROLE_SUPERADMIN])],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(AdminPermission::ALL)],
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
