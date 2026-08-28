<?php

namespace App\Http\Requests\Api\V1\Public;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ListPublicCrusadesRequest extends FormRequest
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
            'country' => ['sometimes', 'string', 'size:2', 'alpha:ascii'],
            'q' => ['sometimes', 'string', 'min:2', 'max:100'],
            'starts_from' => ['sometimes', 'date_format:Y-m-d'],
            'starts_until' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:starts_from'],
            'status' => ['sometimes', Rule::in(['upcoming', 'past', 'all'])],
            'sort' => ['sometimes', Rule::in(['starts_at', '-starts_at', 'name', '-name'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowedKeys = array_keys($this->rules());
            $unexpectedKeys = array_diff(array_keys($this->query()), $allowedKeys);

            foreach ($unexpectedKeys as $unexpectedKey) {
                $validator->errors()->add($unexpectedKey, 'This query parameter is not supported.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        if ($this->query->has('country')) {
            $this->merge(['country' => strtoupper((string) $this->query('country'))]);
        }
    }
}
