<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\AdvisoryAi\Assistant;
use App\AdvisoryAi\UseCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestAdminAdvisoryRequest extends FormRequest
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
            'assistant' => ['required', Rule::enum(Assistant::class)],
            'use_case' => ['required', Rule::enum(UseCase::class)],
            'instruction' => ['required', 'string', 'max:4000'],
            'context' => ['sometimes', 'array'],
        ];
    }
}
