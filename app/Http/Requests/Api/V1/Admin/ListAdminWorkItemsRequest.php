<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Administration\AdminWorkItemPriority;
use App\Administration\AdminWorkItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ListAdminWorkItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array:search,status,priority,assigned_to'],
            'filter.search' => ['sometimes', 'string', 'max:100'],
            'filter.status' => ['sometimes', Rule::enum(AdminWorkItemStatus::class)],
            'filter.priority' => ['sometimes', Rule::enum(AdminWorkItemPriority::class)],
            'filter.assigned_to' => ['sometimes', 'ulid', 'exists:users,public_id'],
            'sort' => ['sometimes', Rule::in(['due_at', '-due_at', 'created_at', '-created_at', 'priority', '-priority'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->query()), ['filter', 'sort', 'page', 'per_page']) as $key) {
                $validator->errors()->add($key, "The {$key} query parameter is not allowed.");
            }
        }];
    }
}
