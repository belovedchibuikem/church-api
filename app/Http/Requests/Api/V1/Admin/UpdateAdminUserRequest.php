<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }

        $profile = $this->input('profile');
        if (! is_array($profile)) {
            return;
        }

        foreach (['phone', 'country', 'region', 'locality', 'middle_name', 'preferred_name'] as $key) {
            if (array_key_exists($key, $profile) && is_string($profile[$key]) && trim($profile[$key]) === '') {
                $profile[$key] = null;
            }
        }

        if (isset($profile['country']) && is_string($profile['country'])) {
            $profile['country'] = strtoupper($profile['country']);
        }

        $this->merge(['profile' => $profile]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:191'],
            'email' => [
                'sometimes',
                'string',
                'email:rfc',
                'max:254',
                Rule::unique('users', 'email')->ignore($this->route('user'), 'public_id'),
            ],
            'profile' => ['sometimes', 'array:given_name,middle_name,family_name,preferred_name,phone,country,region,locality'],
            'profile.given_name' => ['required_with:profile', 'string', 'max:100'],
            'profile.middle_name' => ['nullable', 'string', 'max:100'],
            'profile.family_name' => ['required_with:profile', 'string', 'max:100'],
            'profile.preferred_name' => ['nullable', 'string', 'max:100'],
            'profile.phone' => ['nullable', 'string', 'max:40'],
            'profile.country' => ['nullable', 'string', 'size:2'],
            'profile.region' => ['nullable', 'string', 'max:120'],
            'profile.locality' => ['nullable', 'string', 'max:120'],
        ];
    }
}
