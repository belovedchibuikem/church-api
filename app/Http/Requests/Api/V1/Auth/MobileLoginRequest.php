<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MobileLoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'password' => ['required', 'string', 'max:4096'],
            'device_identifier' => ['required', 'string', 'max:512'],
            'device_label' => ['nullable', 'string', 'max:100'],
            'device_type' => ['nullable', 'string', 'max:50'],
            'platform' => ['nullable', 'string', 'max:100'],
            'app_version' => ['nullable', 'string', 'max:50'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowed = ['email', 'password', 'device_identifier', 'device_label', 'device_type', 'platform', 'app_version'];

            foreach (array_diff(array_keys($this->all()), $allowed) as $key) {
                $validator->errors()->add($key, 'This field is not supported.');
            }
        }];
    }
}
