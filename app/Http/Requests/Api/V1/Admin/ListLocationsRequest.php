<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ListLocationsRequest extends FormRequest
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
            'filter' => ['sometimes', 'array:search,country_id,administrative_unit_id,timezone,has_coordinates'],
            'filter.search' => ['sometimes', 'string', 'max:100'],
            'filter.country_id' => ['sometimes', 'string', Rule::exists('countries', 'public_id')],
            'filter.administrative_unit_id' => ['sometimes', 'string', Rule::exists('administrative_units', 'public_id')],
            'filter.timezone' => ['sometimes', 'string', 'timezone'],
            'filter.has_coordinates' => ['sometimes'],
            'sort' => ['sometimes', Rule::in(['name', '-name', 'created_at', '-created_at'])],
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
