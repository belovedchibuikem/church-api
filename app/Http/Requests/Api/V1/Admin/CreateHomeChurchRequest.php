<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateHomeChurchRequest extends FormRequest
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
            'church_id' => ['required', 'ulid', 'exists:churches,public_id'],
            'leader_person_id' => ['required', 'ulid', 'exists:people,public_id'],
            'location_id' => ['required', 'ulid', 'exists:locations,public_id'],
            'administrative_unit_id' => ['required', 'ulid', 'exists:administrative_units,public_id'],
            'name' => ['required', 'string', 'max:191'],
        ];
    }
}
