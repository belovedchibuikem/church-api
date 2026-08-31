<?php

namespace App\Http\Requests\Api\V1\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StartUserChurchMembershipRequest extends FormRequest
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
            'home_church_id' => ['nullable', 'ulid', 'exists:home_churches,public_id'],
            'confirm_transfer' => ['sometimes', 'boolean'],
        ];
    }
}
