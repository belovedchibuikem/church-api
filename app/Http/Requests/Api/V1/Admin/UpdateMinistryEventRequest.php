<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMinistryEventRequest extends FormRequest
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
            'location_id' => ['nullable', 'ulid', 'exists:locations,public_id'],
            'category_code' => ['sometimes', 'required', 'string', 'max:100', 'regex:/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/'],
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['sometimes', 'required', 'date', 'after_or_equal:starts_at'],
            'registration_opens_at' => ['nullable', 'date'],
            'registration_closes_at' => ['nullable', 'date'],
            'fee_amount_minor' => ['nullable', 'integer', 'min:0'],
            'fee_currency' => ['nullable', 'string', 'size:3'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'is_important' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
