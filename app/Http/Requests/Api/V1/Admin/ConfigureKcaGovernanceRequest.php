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
            'admission_reference_prefix' => ['nullable', 'string', 'max:40'],
            'admission_letter_body_template' => ['nullable', 'string'],
            'admission_programme_commencement' => ['nullable', 'string', 'max:120'],
            'admission_programme_completion' => ['nullable', 'string', 'max:120'],
            'admission_programme_venue' => ['nullable', 'string', 'max:160'],
            'admission_programme_schedule' => ['nullable', 'string', 'max:160'],
            'admission_programme_mentor' => ['nullable', 'string', 'max:160'],
            'orientation_welcome' => ['nullable', 'string'],
            'orientation_review_welcome' => ['nullable', 'string'],
            'admission_letterhead_file_asset_id' => ['nullable', 'string', 'ulid', 'exists:file_assets,public_id'],
            'admission_signature_file_asset_id' => ['nullable', 'string', 'ulid', 'exists:file_assets,public_id'],
        ];
    }
}
