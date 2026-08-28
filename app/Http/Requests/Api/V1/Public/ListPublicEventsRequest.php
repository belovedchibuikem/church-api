<?php

namespace App\Http\Requests\Api\V1\Public;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ListPublicEventsRequest extends FormRequest
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
            'category' => [
                'sometimes',
                'string',
                'max:100',
                'regex:/\A[a-z0-9]+(?:[._-][a-z0-9]+)*\z/',
            ],
            'starts_from' => ['sometimes', 'date_format:Y-m-d'],
            'starts_until' => [
                'sometimes',
                'date_format:Y-m-d',
                Rule::when($this->filled('starts_from'), ['after_or_equal:starts_from']),
            ],
            'sort' => ['sometimes', Rule::in(['starts_at', '-starts_at', 'name', '-name'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowed = ['category', 'starts_from', 'starts_until', 'sort', 'page', 'per_page'];

            foreach (array_diff(array_keys($this->query()), $allowed) as $parameter) {
                $validator->errors()->add('query', "Unsupported query parameter: {$parameter}.");
            }
        }];
    }
}
