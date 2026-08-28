<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EvaluateAlertRuleRequest extends FormRequest
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
            'condition_reference_type' => ['required', 'string', 'max:100'],
            'condition_reference_key' => ['required', 'string', 'max:191'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'facts' => ['nullable', 'array'],
            'scope_type' => ['nullable', 'string', 'max:100'],
            'scope_key' => ['nullable', 'string', 'max:191'],
        ];
    }
}
