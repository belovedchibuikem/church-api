<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Safeguarding\IncidentSeverity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSafeguardingIncidentRequest extends FormRequest
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
            'assigned_to_user_id' => ['sometimes', 'nullable', 'ulid', 'exists:users,public_id'],
            'severity' => ['sometimes', Rule::enum(IncidentSeverity::class)],
            'status' => ['sometimes', 'string', Rule::in(['closed'])],
            'note' => ['sometimes', 'string', 'max:5000'],
        ];
    }
}
