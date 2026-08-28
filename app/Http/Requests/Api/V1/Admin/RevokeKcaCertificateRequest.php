<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RevokeKcaCertificateRequest extends FormRequest
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
            'reason_code' => ['required', 'string', 'max:100', 'regex:/\A[a-z][a-z0-9_]+\z/'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
