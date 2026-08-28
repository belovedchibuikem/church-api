<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\ContentPage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContentPageRequest extends FormRequest
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
        $pagePublicId = (string) $this->route('page');
        $existingId = ContentPage::query()->where('public_id', $pagePublicId)->value('id');

        return [
            'slug' => [
                'sometimes',
                'string',
                'max:100',
                'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/',
                Rule::unique('content_pages', 'slug')->ignore($existingId),
            ],
            'title' => ['sometimes', 'string', 'max:191'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:500'],
            'body' => ['sometimes', 'string'],
            'locale' => ['sometimes', 'string', 'max:35'],
            'published_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
