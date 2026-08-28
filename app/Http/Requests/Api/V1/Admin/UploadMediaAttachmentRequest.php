<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Files\FileAssetClassification;
use App\Media\MediaAttachableType;
use App\Media\MediaRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadMediaAttachmentRequest extends FormRequest
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
            'attachable_type' => ['required', 'string', Rule::in(MediaAttachableType::aliases())],
            'attachable_id' => ['required', 'ulid'],
            'role' => ['required', Rule::enum(MediaRole::class)],
            'purpose' => ['sometimes', 'string', 'max:100'],
            'classification' => ['sometimes', Rule::enum(FileAssetClassification::class)],
        ];
    }
}
