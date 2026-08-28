<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class QueryAdminSearchRequest extends FormRequest
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
            'term' => ['required', 'string', 'min:2', 'max:200'],
            'resource_types' => ['sometimes', 'array'],
            'resource_types.*' => ['required', 'string', 'regex:/\A[a-z][a-z0-9._-]*\z/'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
