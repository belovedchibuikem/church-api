<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConfigureKcaGovernanceRequest extends FormRequest
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
            'pass_threshold_percent' => ['required', 'integer', 'between:1,100'],
            'attendance_threshold_percent' => ['required', 'integer', 'between:1,100'],
            'require_final_assessment' => ['sometimes', 'boolean'],
            'require_signed_pdf' => ['sometimes', 'boolean'],
            'certificate_signer_name' => ['nullable', 'string', 'max:120'],
            'certificate_signer_title' => ['nullable', 'string', 'max:120'],
            'admission_signer_name' => ['nullable', 'string', 'max:120'],
            'admission_signer_title' => ['nullable', 'string', 'max:120'],
            'admission_letterhead_file_asset_id' => ['nullable', 'string', 'ulid', 'exists:file_assets,public_id'],
            'admission_signature_file_asset_id' => ['nullable', 'string', 'ulid', 'exists:file_assets,public_id'],
        ];
    }
}
