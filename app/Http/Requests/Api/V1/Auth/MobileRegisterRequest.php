<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class MobileRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'profile' => ['required', 'array:given_name,middle_name,family_name,preferred_name,country,region,locality'],
            'profile.given_name' => ['required', 'string', 'max:100'],
            'profile.middle_name' => ['nullable', 'string', 'max:100'],
            'profile.family_name' => ['required', 'string', 'max:100'],
            'profile.preferred_name' => ['nullable', 'string', 'max:100'],
            'profile.country' => ['nullable', 'string', 'size:2'],
            'profile.region' => ['nullable', 'string', 'max:120'],
            'profile.locality' => ['nullable', 'string', 'max:120'],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:254',
                Rule::unique('users', 'email'),
            ],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(12)->mixedCase()->letters()->numbers()->symbols(),
            ],
            'password_confirmation' => ['required', 'string'],
            'device_identifier' => ['required', 'string', 'max:512'],
            'device_label' => ['nullable', 'string', 'max:100'],
            'device_type' => ['nullable', 'string', 'max:50'],
            'platform' => ['nullable', 'string', 'max:100'],
            'app_version' => ['nullable', 'string', 'max:50'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
        }

        $profile = $this->input('profile');
        if (! is_array($profile)) {
            return;
        }

        foreach (['country', 'region', 'locality'] as $key) {
            if (! array_key_exists($key, $profile) || ! is_string($profile[$key])) {
                continue;
            }

            $value = trim((string) $profile[$key]);
            if ($key === 'country') {
                $value = strtoupper($value);
            }
            $profile[$key] = $value === '' ? null : $value;
        }

        $this->merge(['profile' => $profile]);
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowed = [
                'profile',
                'email',
                'password',
                'password_confirmation',
                'device_identifier',
                'device_label',
                'device_type',
                'platform',
                'app_version',
            ];

            if (array_diff(array_keys($this->all()), $allowed) !== []) {
                $validator->errors()->add('request', 'The request contains unsupported fields.');
            }
        }];
    }
}
