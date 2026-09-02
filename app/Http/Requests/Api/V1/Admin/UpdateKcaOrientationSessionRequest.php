<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateKcaOrientationSessionRequest extends FormRequest
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
            'cohort_id' => ['sometimes', 'nullable', 'ulid', 'exists:kca_cohorts,public_id'],
            'location_id' => ['sometimes', 'nullable', 'ulid', 'exists:locations,public_id'],
            'name' => ['sometimes', 'string', 'max:191'],
            'venue_label' => ['sometimes', 'nullable', 'string', 'max:191'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'published_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
