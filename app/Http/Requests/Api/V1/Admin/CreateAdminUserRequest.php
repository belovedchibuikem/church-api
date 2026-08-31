<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:254', Rule::unique('users', 'email')],
            'profile' => ['required', 'array:given_name,middle_name,family_name,preferred_name,country,region,locality'],
            'profile.given_name' => ['required', 'string', 'max:100'],
            'profile.middle_name' => ['nullable', 'string', 'max:100'],
            'profile.family_name' => ['required', 'string', 'max:100'],
            'profile.preferred_name' => ['nullable', 'string', 'max:100'],
            'profile.country' => ['nullable', 'string', 'size:2'],
            'profile.region' => ['nullable', 'string', 'max:120'],
            'profile.locality' => ['nullable', 'string', 'max:120'],
            'role_id' => ['nullable', 'ulid', 'exists:roles,public_id'],
        ];
    }
}
