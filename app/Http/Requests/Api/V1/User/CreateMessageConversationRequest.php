<?php

namespace App\Http\Requests\Api\V1\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateMessageConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'participant_person_ids' => ['required', 'array', 'min:1'],
            'participant_person_ids.*' => ['required', 'ulid', 'distinct', 'exists:people,public_id'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:191'],
            'first_message' => ['required', 'string', 'max:10000'],
        ];
    }
}
