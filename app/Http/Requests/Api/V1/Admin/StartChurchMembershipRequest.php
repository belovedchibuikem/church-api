<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StartChurchMembershipRequest extends FormRequest
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
            'church_id' => ['required', 'ulid', 'exists:churches,public_id'],
            'home_church_id' => ['nullable', 'ulid', 'exists:home_churches,public_id'],
            'joined_at' => ['nullable', 'date'],
        ];
    }
}
