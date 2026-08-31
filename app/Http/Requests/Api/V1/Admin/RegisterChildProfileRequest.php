<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Safeguarding\MinorStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterChildProfileRequest extends FormRequest
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
            'person_id' => ['required', 'ulid', 'exists:people,public_id'],
            'date_of_birth' => ['nullable', 'date'],
            'minor_status' => ['sometimes', Rule::enum(MinorStatus::class)],
            'direct_communication_restricted' => ['sometimes', 'boolean'],
            'media_use_restricted' => ['sometimes', 'boolean'],
        ];
    }
}
