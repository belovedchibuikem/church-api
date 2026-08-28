<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Reporting\AlertSeverity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAlertRuleRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:191'],
            'condition_type' => ['required', 'string', 'max:100'],
            'severity' => ['required', Rule::enum(AlertSeverity::class)],
            'configuration' => ['required', 'array'],
            'scope_type' => ['nullable', 'string', 'max:100'],
            'scope_key' => ['nullable', 'string', 'max:191'],
        ];
    }
}
