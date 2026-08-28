<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreContentItemRequest extends FormRequest
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
            'kind' => ['required', 'string', 'max:40'],
            'title' => ['required', 'string', 'max:191'],
            'body' => ['required', 'string'],
            'meta' => ['sometimes', 'nullable', 'array'],
            'href' => ['sometimes', 'nullable', 'string', 'max:500'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'published_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
