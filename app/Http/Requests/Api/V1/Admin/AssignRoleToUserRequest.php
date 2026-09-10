<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AssignRoleToUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role_id' => ['required', 'ulid', 'exists:roles,public_id'],
            'expires_at' => ['nullable', 'date'],
            'scope_type' => ['nullable', 'string', 'in:church,home_church,country,administrative_unit,global'],
            'scope_key' => ['nullable', 'required_with:scope_type', 'string', 'max:191'],
        ];
    }
}
