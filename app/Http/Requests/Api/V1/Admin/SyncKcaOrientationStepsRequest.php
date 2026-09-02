<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncKcaOrientationStepsRequest extends FormRequest
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
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.id' => ['nullable', 'string', 'ulid', Rule::exists('kca_orientation_steps', 'public_id')],
            'steps.*.slug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'steps.*.title' => ['required', 'string', 'max:191'],
            'steps.*.subtitle' => ['nullable', 'string', 'max:191'],
            'steps.*.body' => ['nullable', 'string'],
            'steps.*.display_type' => ['required', 'string', Rule::in(['content', 'modules_list', 'mentor'])],
            'steps.*.sequence' => ['required', 'integer', 'min:1'],
            'steps.*.is_active' => ['sometimes', 'boolean'],
        ];
    }
}
