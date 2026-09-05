<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Kca\KcaApplicationState;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkAdmitKcaStudentsRequest extends FormRequest
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
            'application_ids' => ['required', 'array', 'min:1', 'max:100'],
            'application_ids.*' => ['required', 'ulid', 'distinct', 'exists:kca_applications,public_id'],
            'cohort_id' => ['required', 'ulid', 'exists:kca_cohorts,public_id'],
            'starts_on' => ['required', 'date'],
            'status' => [
                'nullable',
                Rule::in([
                    KcaApplicationState::Accepted->value,
                    KcaApplicationState::ProvisionallyAccepted->value,
                ]),
            ],
        ];
    }
}
