<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'kca_enrollment_id' => ['required', 'ulid', 'exists:kca_enrollments,public_id'],
            'kca_module_id' => ['required', 'ulid', 'exists:kca_modules,public_id'],
            'title' => ['required', 'string', 'max:191'],
            'assignment_kind' => ['nullable', Rule::in(['standard', 'soul_winning'])],
            'soul_tree_levels' => ['nullable', 'array', 'min:1'],
            'soul_tree_levels.*' => ['integer', 'min:1', 'max:50'],
            'due_at' => ['nullable', 'date'],
        ];
    }
}
