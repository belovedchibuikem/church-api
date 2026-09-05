<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Kca\KcaApplicationState;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkTransitionKcaApplicationsRequest extends FormRequest
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
            'status' => ['required', Rule::enum(KcaApplicationState::class)],
            'reason_code' => [
                Rule::requiredIf(fn (): bool => KcaApplicationState::tryFrom((string) $this->input('status'))?->requiresDecisionReason() ?? false),
                'nullable',
                'string',
                'max:100',
                'regex:/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/',
            ],
        ];
    }
}
