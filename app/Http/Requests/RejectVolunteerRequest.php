<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectVolunteerRequest extends FormRequest
{
    /**
     * Route middleware enforces admin/superadmin permission checks.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Optional rejection reason validation.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
