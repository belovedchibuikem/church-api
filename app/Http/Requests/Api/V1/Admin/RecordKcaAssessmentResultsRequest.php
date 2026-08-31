<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RecordKcaAssessmentResultsRequest extends FormRequest
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
            'audience' => ['required', 'string', Rule::in(['student', 'year', 'all', 'One student', 'A student year', 'All enrolled students'])],
            'enrollment_id' => ['nullable', 'ulid', 'exists:kca_enrollments,public_id'],
            'kca_enrollment_id' => ['nullable', 'ulid', 'exists:kca_enrollments,public_id'],
            'year_id' => ['nullable', 'ulid', 'exists:kca_years,public_id'],
            'kca_module_id' => ['nullable', 'ulid', 'exists:kca_modules,public_id'],
            'kca_lesson_id' => ['nullable', 'ulid', 'exists:kca_lessons,public_id'],
            'assessment_code' => ['required', 'string', 'max:100'],
            'result_code' => ['required', 'string', 'max:100'],
            'score' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $audience = strtolower((string) $this->input('audience'));
            $enrollment = $this->input('enrollment_id') ?: $this->input('kca_enrollment_id');
            if (str_contains($audience, 'student') && ! str_contains($audience, 'year') && ! str_contains($audience, 'all') && blank($enrollment)) {
                $validator->errors()->add('kca_enrollment_id', 'Select the student to assess.');
            }
            if (str_contains($audience, 'year') && blank($this->input('year_id'))) {
                $validator->errors()->add('year_id', 'Select the KCA year to assess.');
            }
        });
    }
}
