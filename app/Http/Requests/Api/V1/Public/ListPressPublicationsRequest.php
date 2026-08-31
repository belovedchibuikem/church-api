<?php

namespace App\Http\Requests\Api\V1\Public;

use App\Press\LanguageCode;
use App\Press\PressPublicationFormat;
use App\Press\PressPublicationType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ListPressPublicationsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array:language,category,format,publication_type'],
            'filter.language' => ['sometimes', 'string', 'max:35', 'regex:/\A[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*\z/'],
            'filter.category' => ['sometimes', 'string', 'max:100'],
            'filter.format' => ['sometimes', Rule::enum(PressPublicationFormat::class)],
            'filter.publication_type' => ['sometimes', Rule::enum(PressPublicationType::class)],
            'sort' => ['sometimes', 'string', Rule::in([
                'publication_date',
                '-publication_date',
                'title',
                '-title',
            ])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowedKeys = ['filter', 'sort', 'page', 'per_page'];

            foreach (array_diff(array_keys($this->query->all()), $allowedKeys) as $key) {
                $validator->errors()->add($key, "The {$key} query parameter is not allowed.");
            }
        }];
    }

    /** @return array{language?: string, category?: string, format?: string, publication_type?: string} */
    public function filters(): array
    {
        $validated = $this->validated();
        $filters = $validated['filter'] ?? [];

        if (isset($filters['language'])) {
            $filters['language'] = LanguageCode::normalize($filters['language']);
        }

        return $filters;
    }

    public function sort(): string
    {
        return $this->validated('sort', '-publication_date');
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', 20);
    }
}
