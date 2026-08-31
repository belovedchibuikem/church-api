<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Church\MeetingDay;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAdminHomeChurchApplicationRequest extends FormRequest
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
            'applicant_person_id' => ['required', 'ulid', 'exists:people,public_id'],
            'church_id' => ['required', 'ulid', 'exists:churches,public_id'],
            'location_id' => ['required', 'ulid', 'exists:locations,public_id'],
            'administrative_unit_id' => ['required', 'ulid', 'exists:administrative_units,public_id'],
            'proposed_name' => ['nullable', 'string', 'max:191'],
            'residence_family_name' => ['nullable', 'string', 'max:100'],
            'expected_participants' => ['required', 'integer', 'min:1', 'max:65535'],
            'meeting_day' => ['required_without:meeting_schedules', 'nullable', Rule::enum(MeetingDay::class)],
            'meeting_time' => ['required_without:meeting_schedules', 'nullable', 'date_format:H:i'],
            'meeting_schedules' => ['nullable', 'array', 'min:1'],
            'meeting_schedules.*.day' => ['required', Rule::enum(MeetingDay::class)],
            'meeting_schedules.*.time' => ['required', 'date_format:H:i'],
            'meeting_schedules.*.activity' => ['nullable', 'string', 'max:80'],
            'contact_email' => ['required', 'email', 'max:254'],
            'contact_phone' => ['required', 'string', 'max:32'],
            'guidelines_agreed_at' => ['required', 'date'],
        ];
    }
}
