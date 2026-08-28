<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AssignPrayerRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'assigned_to_person_id' => ['required', 'ulid', 'exists:people,public_id'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
