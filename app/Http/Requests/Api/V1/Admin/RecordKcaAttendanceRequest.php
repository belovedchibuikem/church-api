<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Kca\KcaAttendanceStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordKcaAttendanceRequest extends FormRequest
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
            'status' => ['required', Rule::enum(KcaAttendanceStatus::class)],
            'session_on' => ['required', 'date'],
        ];
    }
}
