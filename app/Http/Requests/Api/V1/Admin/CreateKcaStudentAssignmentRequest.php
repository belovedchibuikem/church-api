<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CreateKcaStudentAssignmentRequest extends FormRequest
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
            'audience' => [
                'sometimes',
                'string',
                Rule::in([
                    'student',
                    'cohort',
                    'all',
                    'One student',
                    'A cohort',
                    'All enrolled students',
                ]),
            ],
            'kca_enrollment_id' => ['nullable', 'ulid', 'exists:kca_enrollments,public_id'],
            'cohort_id' => ['nullable', 'ulid', 'exists:kca_cohorts,public_id'],
            'kca_module_id' => ['required', 'ulid', 'exists:kca_modules,public_id'],
            'kca_lesson_id' => ['required', 'ulid', 'exists:kca_lessons,public_id'],
            'title' => ['required', 'string', 'max:191'],
            'assignment_kind' => ['nullable', Rule::in(['standard', 'soul_winning', 'practical', 'written'])],
            'soul_tree_levels' => ['nullable', 'array', 'min:1'],
            'soul_tree_levels.*' => ['integer', 'min:1', 'max:50'],
            'due_at' => ['nullable', 'date'],
            'as_draft' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $audience = strtolower((string) ($this->input('audience') ?: 'student'));
            $enrollment = $this->input('kca_enrollment_id') ?: $this->input('enrollment_id');
            $cohort = $this->input('cohort_id') ?: $this->input('kca_cohort_id');

            $isStudent = $audience === 'student' || str_contains($audience, 'one');
            $isCohort = str_contains($audience, 'cohort');
            $isAll = str_contains($audience, 'all');

            if ($isStudent && ! $isAll && blank($enrollment)) {
                $validator->errors()->add('kca_enrollment_id', 'Select the student to assign.');
            }
            if ($isCohort && blank($cohort)) {
                $validator->errors()->add('cohort_id', 'Select the cohort to assign.');
            }
        });
    }
}
