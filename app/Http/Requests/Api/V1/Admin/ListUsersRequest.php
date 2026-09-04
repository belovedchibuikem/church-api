<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Identity\UserAccountStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ListUsersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $filter = $this->input('filter');
        if (! is_array($filter)) {
            return;
        }

        foreach (['email_verified', 'exclude_app_members'] as $key) {
            if (! array_key_exists($key, $filter)) {
                continue;
            }
            $value = $filter[$key];
            if (is_bool($value)) {
                continue;
            }
            if (is_int($value) || is_float($value)) {
                $filter[$key] = ((int) $value) === 1;
                continue;
            }
            if (! is_string($value)) {
                continue;
            }
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['true', '1', 'yes', 'on'], true)) {
                $filter[$key] = true;
            } elseif (in_array($normalized, ['false', '0', 'no', 'off'], true)) {
                $filter[$key] = false;
            }
        }

        $this->merge(['filter' => $filter]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array:search,status,email_verified,exclude_app_members'],
            'filter.search' => ['sometimes', 'string', 'max:100'],
            'filter.status' => ['sometimes', Rule::enum(UserAccountStatus::class)],
            'filter.email_verified' => ['sometimes', 'boolean'],
            'filter.exclude_app_members' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', Rule::in(['name', '-name', 'email', '-email', 'created_at', '-created_at'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [$this->rejectUnsupportedQueryParameters(...)];
    }

    /** @return array{search?: string, status?: string, email_verified?: bool, exclude_app_members?: bool} */
    public function filters(): array
    {
        /** @var array{search?: string, status?: string, email_verified?: bool, exclude_app_members?: bool} $filters */
        $filters = $this->validated('filter', []);

        return $filters;
    }

    public function sort(): string
    {
        return (string) $this->validated('sort', '-created_at');
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', 25);
    }

    private function rejectUnsupportedQueryParameters(Validator $validator): void
    {
        foreach (array_diff(array_keys($this->query()), ['filter', 'sort', 'page', 'per_page']) as $key) {
            $validator->errors()->add($key, "The {$key} query parameter is not allowed.");
        }
    }
}
