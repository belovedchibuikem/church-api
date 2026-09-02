<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IssueKcaAdmissionLetterRequest extends FormRequest
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
            'batch_label' => ['nullable', 'string', 'max:160'],
            'letter_body' => ['nullable', 'string', 'max:10000'],
            'signer_name' => ['nullable', 'string', 'max:120'],
            'signer_title' => ['nullable', 'string', 'max:120'],
            'letterhead_file_asset_id' => ['nullable', 'string', 'ulid', 'exists:file_assets,public_id'],
            'signature_file_asset_id' => ['nullable', 'string', 'ulid', 'exists:file_assets,public_id'],
        ];
    }
}
