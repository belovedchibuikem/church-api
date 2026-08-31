<?php

namespace App\Http\Requests\Api\V1\User;

use App\Finance\GivingPurpose;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateGivingIntentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key') ?? $this->input('idempotency_key'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'between:8,191'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3', 'regex:/\A[A-Za-z]{3}\z/'],
            'purpose_code' => ['required', 'string', Rule::in(GivingPurpose::codes())],
            'proof_file_asset_id' => ['sometimes', 'ulid', Rule::exists('file_assets', 'public_id')],
            'checkout_return' => ['sometimes', 'string', 'in:web,mobile'],
        ];
    }
}
