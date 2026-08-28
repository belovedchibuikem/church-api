<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EnrollKcaStudentRequest extends FormRequest
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
            'cohort_id' => ['required', 'ulid', 'exists:kca_cohorts,public_id'],
            'registration_number' => ['required', 'string', 'max:100'],
            'starts_on' => ['required', 'date'],
        ];
    }
}
