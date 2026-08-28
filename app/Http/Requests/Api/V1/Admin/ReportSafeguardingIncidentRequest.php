<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Safeguarding\IncidentSeverity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportSafeguardingIncidentRequest extends FormRequest
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
            'concern_type' => ['required', 'string', 'max:100'],
            'severity' => ['required', Rule::enum(IncidentSeverity::class)],
            'restricted_summary' => ['required', 'string', 'max:5000'],
            'subject_person_id' => ['nullable', 'ulid', 'exists:people,public_id'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }
}
