<?php

namespace App\Http\Requests\Api\V1\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserMissionInvitationRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:191'],
            'type' => ['nullable', 'string', 'max:80'],
            'start' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:191'],
            'details' => ['nullable', 'string', 'max:10000'],
            'purpose' => ['nullable', 'string', 'max:500'],
            'expected_attendance' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'idempotency_key' => ['nullable', 'string', 'min:8', 'max:191'],
            'requested_location_id' => ['nullable', 'ulid', 'exists:locations,public_id'],
        ];
    }
}
