<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateKcaEnrollmentRequest extends FormRequest
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
            'cohort_id' => ['sometimes', 'ulid', 'exists:kca_cohorts,public_id'],
            'kca_cohort_id' => ['sometimes', 'ulid', 'exists:kca_cohorts,public_id'],
            'registration_number' => ['sometimes', 'string', 'min:1', 'max:100'],
            'starts_on' => ['sometimes', 'date'],
            'given_name' => ['sometimes', 'string', 'max:100'],
            'family_name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:50'],
            'application_data' => ['sometimes', 'array'],
        ];
    }
}
