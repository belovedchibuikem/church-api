<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BeginDataExportRequest extends FormRequest
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
            'data_categories' => ['required', 'array', 'min:1'],
            'data_categories.*' => ['required', 'string', 'max:100'],
            'scope_type' => ['nullable', 'string', 'max:100'],
            'scope_key' => ['nullable', 'string', 'max:191'],
        ];
    }
}
