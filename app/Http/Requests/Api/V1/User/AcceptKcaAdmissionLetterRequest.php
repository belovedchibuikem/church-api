<?php

namespace App\Http\Requests\Api\V1\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AcceptKcaAdmissionLetterRequest extends FormRequest
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
            'applicant_signature_name' => ['required', 'string', 'max:160'],
            'applicant_signature_file_asset_id' => ['nullable', 'string', 'ulid', 'exists:file_assets,public_id'],
            'guardian_name' => ['nullable', 'string', 'max:160'],
            'guardian_signature_name' => ['nullable', 'string', 'max:160'],
            'guardian_phone' => ['nullable', 'string', 'max:40'],
        ];
    }
}
