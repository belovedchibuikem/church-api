<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ListPlatformConfigurationsRequest extends FormRequest
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
            'filter' => ['sometimes', 'array:search,environment,classification'],
            'filter.search' => ['sometimes', 'string', 'max:100'],
            'filter.environment' => ['sometimes', 'string', 'max:50', 'regex:/\A(?:\*|[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*)\z/'],
            'filter.classification' => ['sometimes', Rule::in(['internal', 'confidential'])],
            'sort' => ['sometimes', Rule::in(['key', '-key', 'updated_at', '-updated_at'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [$this->rejectUnsupportedQueryParameters(...)];
    }

    private function rejectUnsupportedQueryParameters(Validator $validator): void
    {
        foreach (array_diff(array_keys($this->query()), ['filter', 'sort', 'page', 'per_page']) as $key) {
            $validator->errors()->add($key, "The {$key} query parameter is not allowed.");
        }
    }
}
