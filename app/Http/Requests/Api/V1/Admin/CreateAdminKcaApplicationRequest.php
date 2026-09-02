<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class CreateAdminKcaApplicationRequest extends FormRequest
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
            'application_id' => ['nullable', 'ulid', 'exists:kca_applications,public_id'],
            'person_id' => ['nullable', 'ulid', 'exists:people,public_id', 'required_without_all:application_id,given_name'],
            'given_name' => ['required_without_all:application_id,person_id', 'string', 'max:120'],
            'family_name' => ['required_without_all:application_id,person_id', 'string', 'max:120'],
            'email' => ['required_if:create_login,true', 'nullable', 'email', 'max:254'],
            'phone' => ['nullable', 'string', 'max:32'],
            'create_login' => ['sometimes', 'boolean'],
            'password' => ['required_if:create_login,true', 'nullable', 'string', 'min:8', 'confirmed'],
            'application_data' => ['present', 'array'],
            'application_data.*' => ['nullable', 'string', 'max:5000'],
            'finalize' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->boolean('finalize') && count($this->input('application_data', [])) < 1) {
                $validator->errors()->add('application_data', 'Application data is required when submitting.');
            }

            if ($this->boolean('create_login') && ! $this->boolean('finalize')) {
                $validator->errors()->add('create_login', 'Login accounts can only be created when submitting the application.');
            }
        });
    }
}
