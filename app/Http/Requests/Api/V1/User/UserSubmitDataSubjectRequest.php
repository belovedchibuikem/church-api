<?php

namespace App\Http\Requests\Api\V1\User;

use App\Privacy\DataSubjectRequestType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserSubmitDataSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key') ?? $this->input('idempotency_key')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'between:8,191'],
            'request_type' => ['required', Rule::enum(DataSubjectRequestType::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
