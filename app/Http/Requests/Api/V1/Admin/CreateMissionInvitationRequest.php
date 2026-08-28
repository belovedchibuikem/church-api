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
            'crusade_id' => ['required', 'ulid', 'exists:crusades,public_id'],
            'requester_person_id' => ['required', 'ulid', 'exists:people,public_id'],
            'requested_location_id' => ['required', 'ulid', 'exists:locations,public_id'],
        ];
    }
}
