<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Kca\KcaPrerequisiteRequirement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateKcaModulePrerequisiteRequest extends FormRequest
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
            'prerequisite_module_id' => ['required', 'ulid', 'exists:kca_modules,public_id'],
            'requirement' => ['required', Rule::enum(KcaPrerequisiteRequirement::class)],
        ];
    }
}
