<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateKcaLessonRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:191'],
            'sequence' => ['required', 'integer', 'min:1', 'max:65535'],
            'day_index' => ['nullable', 'integer', 'min:1', 'max:365'],
            'lesson_type' => ['nullable', 'string', 'max:40'],
            'summary' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:20000'],
            'content_url' => ['nullable', 'string', 'max:2048'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'requires_acknowledgement' => ['sometimes', 'boolean'],
        ];
    }
}
