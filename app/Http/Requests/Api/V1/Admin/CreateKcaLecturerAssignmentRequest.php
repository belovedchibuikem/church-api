<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateKcaLecturerAssignmentRequest extends FormRequest
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
            'kca_module_id' => ['required', 'ulid', 'exists:kca_modules,public_id'],
            'kca_lesson_id' => ['required', 'ulid', 'exists:kca_lessons,public_id'],
            'kca_cohort_id' => ['required', 'ulid', 'exists:kca_cohorts,public_id'],
            'lecturer_person_id' => ['required', 'ulid', 'exists:people,public_id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ];
    }
}
