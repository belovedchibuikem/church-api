<?php

namespace App\Http\Requests\Api\V1\User;

use App\Files\FileAssetClassification;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserStoreFileAssetRequest extends FormRequest
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
            'file' => ['required', 'file'],
            'purpose' => ['required', 'string', 'max:100'],
            'classification' => ['required', Rule::enum(FileAssetClassification::class)],
        ];
    }
}
