<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Kca\KcaAssignmentState;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewKcaEvidenceRequest extends FormRequest
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
            'reviewer_person_id' => ['required', 'ulid', 'exists:people,public_id'],
            'outcome' => ['required', Rule::enum(KcaAssignmentState::class)],
        ];
    }
}
