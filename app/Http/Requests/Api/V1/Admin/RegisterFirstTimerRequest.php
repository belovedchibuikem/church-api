<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterFirstTimerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'person_id' => ['required', 'ulid', 'exists:people,public_id'],
            'church_id' => ['required', 'ulid', 'exists:churches,public_id'],
            'home_church_id' => ['nullable', 'ulid', 'exists:home_churches,public_id'],
            'assigned_follow_up_person_id' => ['nullable', 'ulid', 'exists:people,public_id'],
            'registered_at' => ['nullable', 'date'],
        ];
    }
}
