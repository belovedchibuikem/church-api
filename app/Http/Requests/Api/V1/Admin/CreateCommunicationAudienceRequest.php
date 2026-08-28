<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Communication\CommunicationAudienceRuleType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCommunicationAudienceRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:191'],
            'rules' => ['required', 'array', 'min:1', 'max:50'],
            'rules.*.type' => ['required', Rule::enum(CommunicationAudienceRuleType::class)],
            'rules.*.selector_key' => ['nullable', 'string', 'max:191'],
            'rules.*.scope_type' => ['nullable', 'string', 'max:100'],
            'rules.*.scope_key' => ['nullable', 'string', 'max:191'],
        ];
    }
}
