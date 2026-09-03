<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Kca\KcaAttendanceStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordKcaMassAttendanceRequest extends FormRequest
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
            'lesson_id' => ['required', 'ulid', 'exists:kca_lessons,public_id'],
            'session_on' => ['required', 'date'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.enrollment_id' => ['required', 'ulid', 'exists:kca_enrollments,public_id'],
            'records.*.status' => ['required', Rule::enum(KcaAttendanceStatus::class)],
        ];
    }
}
