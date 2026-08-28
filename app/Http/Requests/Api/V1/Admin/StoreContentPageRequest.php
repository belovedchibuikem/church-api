<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreContentPageRequest extends FormRequest
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
            'slug' => ['required', 'string', 'max:100', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', 'unique:content_pages,slug'],
            'title' => ['required', 'string', 'max:191'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:500'],
            'body' => ['required', 'string'],
            'locale' => ['sometimes', 'string', 'max:35'],
            'published_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
