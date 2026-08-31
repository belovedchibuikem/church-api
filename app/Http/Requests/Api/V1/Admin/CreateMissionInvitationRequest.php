<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateMissionInvitationRequest extends FormRequest
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
            'crusade_id' => ['nullable', 'ulid', 'exists:crusades,public_id'],
            'requester_person_id' => ['required', 'ulid', 'exists:people,public_id'],
            'requested_location_id' => ['nullable', 'ulid', 'exists:locations,public_id'],
            'purpose' => ['nullable', 'string', 'max:500'],
            'expected_attendance' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'idempotency_key' => ['nullable', 'string', 'min:8', 'max:191'],
            'application_data' => ['nullable', 'array'],
        ];
    }
}
