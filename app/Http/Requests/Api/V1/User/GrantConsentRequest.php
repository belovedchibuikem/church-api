<?php

namespace App\Http\Requests\Api\V1\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GrantConsentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'purpose' => [
                'required',
                'string',
                'max:100',
                'regex:/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/',
            ],
            'policy_version' => [
                'required',
                'string',
                'max:100',
                'regex:/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/',
            ],
        ];
    }
}
