<?php

namespace App\Http\Requests\Api\V1\Public;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ListChurchesRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array:name,country,administrative_unit'],
            'filter.name' => ['sometimes', 'string', 'max:100'],
            'filter.country' => ['sometimes', 'string', 'regex:/\A[A-Za-z]{2}\z/'],
            'filter.administrative_unit' => ['sometimes', 'string', 'ulid'],
            'sort' => ['sometimes', 'string', Rule::in(['name', '-name', 'published_at', '-published_at'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $unsupported = array_diff(array_keys($this->query()), ['filter', 'sort', 'page', 'per_page']);

            if ($unsupported !== []) {
                $validator->errors()->add('query', 'The request contains unsupported query parameters.');
            }
        }];
    }

    /** @return array{name?: string, country?: string, administrative_unit?: string} */
    public function filters(): array
    {
        /** @var array{name?: string, country?: string, administrative_unit?: string} $filters */
        $filters = $this->validated('filter', []);

        return $filters;
    }

    public function sort(): string
    {
        return (string) $this->validated('sort', 'name');
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', 15);
    }
}
