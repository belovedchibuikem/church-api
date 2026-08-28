<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RequestPaymentRefundRequest extends FormRequest
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
            'amount_minor' => ['required', 'integer', 'min:1'],
            'reason_code' => ['required', 'string', 'max:100', 'regex:/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/'],
        ];
    }
}
