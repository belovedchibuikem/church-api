<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Administration\AdminWorkItemPriority;
use App\Administration\AdminWorkItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminWorkItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:191'],
            'body' => ['nullable', 'string', 'max:5000'],
            'priority' => ['sometimes', Rule::enum(AdminWorkItemPriority::class)],
            'due_at' => ['nullable', 'date'],
            'assigned_to_user_id' => ['nullable', 'ulid', 'exists:users,public_id'],
            'status' => ['sometimes', Rule::enum(AdminWorkItemStatus::class)],
        ];
    }
}
