<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:191'],
            'profile' => ['sometimes', 'array:given_name,middle_name,family_name,preferred_name'],
            'profile.given_name' => ['required_with:profile', 'string', 'max:100'],
            'profile.middle_name' => ['nullable', 'string', 'max:100'],
            'profile.family_name' => ['required_with:profile', 'string', 'max:100'],
            'profile.preferred_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
