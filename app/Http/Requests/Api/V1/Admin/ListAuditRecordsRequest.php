<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAuditRecordsRequest extends FormRequest
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
            'filter' => ['sometimes', 'array:actor_id,action,target_type,target_types,permission_code,allowed,from,to'],
            'filter.actor_id' => ['sometimes', 'ulid', 'exists:users,public_id'],
            'filter.action' => ['sometimes', 'string', 'max:191'],
            'filter.target_type' => ['sometimes', 'string', 'max:191'],
            'filter.target_types' => ['sometimes', 'string', 'max:500'],
            'filter.permission_code' => ['sometimes', 'string', 'max:191'],
            'filter.allowed' => ['sometimes', 'boolean'],
            'filter.from' => ['sometimes', 'date'],
            'filter.to' => ['sometimes', 'date', 'after_or_equal:filter.from'],
            'format' => ['sometimes', Rule::in(['json', 'csv'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
