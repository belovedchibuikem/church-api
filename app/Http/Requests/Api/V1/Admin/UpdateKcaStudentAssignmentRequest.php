<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKcaStudentAssignmentRequest extends FormRequest
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
            'kca_module_id' => ['sometimes', 'required', 'ulid', 'exists:kca_modules,public_id'],
            'kca_lesson_id' => ['sometimes', 'required', 'ulid', 'exists:kca_lessons,public_id'],
            'title' => ['sometimes', 'required', 'string', 'max:191'],
            'due_at' => ['nullable', 'date'],
            'soul_tree_levels' => ['nullable', 'array', 'min:1'],
            'soul_tree_levels.*' => ['integer', 'min:1', 'max:50'],
            'assignment_kind' => ['sometimes', 'required', Rule::in(['standard', 'soul_winning', 'practical', 'written'])],
        ];
    }
}
