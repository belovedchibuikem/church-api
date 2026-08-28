<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterGuardianRelationshipRequest extends FormRequest
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
            'guardian_person_id' => ['required', 'ulid', 'exists:people,public_id'],
            'child_person_id' => ['required', 'ulid', 'exists:people,public_id'],
            'relationship_type' => ['required', 'string', 'max:50'],
        ];
    }
}
