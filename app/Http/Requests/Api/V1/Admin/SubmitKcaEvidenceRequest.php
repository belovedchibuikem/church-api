<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubmitKcaEvidenceRequest extends FormRequest
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
            'idempotency_key' => ['required', 'string', 'between:1,255'],
            'enrollment_id' => ['required', 'ulid', 'exists:kca_enrollments,public_id'],
            'file_asset_id' => ['required', 'ulid', 'exists:file_assets,public_id'],
            'submitted_by_person_id' => ['required', 'ulid', 'exists:people,public_id'],
        ];
    }
}
