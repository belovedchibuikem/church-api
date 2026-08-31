<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Press\PressReviewDecision;
use App\Press\PressReviewStage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePressPublicationReviewRequest extends FormRequest
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
            'stage' => ['required', Rule::enum(PressReviewStage::class)],
            'decision' => ['required', Rule::enum(PressReviewDecision::class)],
            'reviewer_person_id' => ['nullable', 'ulid', 'exists:people,public_id'],
            'comments' => ['nullable', 'string', 'max:10000'],
            'requested_changes' => ['nullable', 'string', 'max:10000'],
            'checklist' => ['nullable', 'array'],
            'comments_public' => ['sometimes', 'boolean'],
        ];
    }
}
