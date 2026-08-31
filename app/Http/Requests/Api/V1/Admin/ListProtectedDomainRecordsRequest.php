<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ListProtectedDomainRecordsRequest extends FormRequest
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
            'filter' => ['sometimes', 'array:search,status,purpose,crusade_id,role_code,home_church_id,church_id'],
            'filter.search' => ['sometimes', 'string', 'max:100'],
            'filter.status' => ['sometimes', 'string', 'max:100', 'regex:/\A[a-z][a-z0-9_-]*\z/'],
            'filter.purpose' => ['sometimes', 'string', 'max:100', 'regex:/\A[a-z][a-z0-9_-]*\z/'],
            'filter.crusade_id' => ['sometimes', 'ulid'],
            'filter.role_code' => ['sometimes', 'string', 'max:100'],
            'filter.home_church_id' => ['sometimes', 'ulid', 'exists:home_churches,public_id'],
            'filter.church_id' => ['sometimes', 'ulid', 'exists:churches,public_id'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->query()), ['filter', 'page', 'per_page']) as $key) {
                $validator->errors()->add($key, "The {$key} query parameter is not allowed.");
            }
        }];
    }
}
