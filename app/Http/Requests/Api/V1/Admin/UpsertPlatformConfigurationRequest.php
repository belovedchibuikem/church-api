<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpsertPlatformConfigurationRequest extends FormRequest
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
            'key' => ['required', 'string', 'max:191', 'regex:/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)+\z/'],
            'value_type' => ['required', Rule::in(['string', 'integer', 'boolean', 'json'])],
            'classification' => ['required', Rule::in(['internal', 'confidential'])],
            'value' => ['present'],
            'environment' => ['required', 'string', 'max:50', 'regex:/\A(?:\*|[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*)\z/'],
            'scope_type' => ['nullable', 'required_with:scope_id', 'string', 'max:100', 'regex:/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/'],
            'scope_id' => ['nullable', 'required_with:scope_type', 'string', 'max:64', 'regex:/\A[^\s\x00-\x1F\x7F]+\z/u'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $value = $this->input('value');
            $valid = match ($this->input('value_type')) {
                'string' => is_string($value),
                'integer' => is_int($value),
                'boolean' => is_bool($value),
                'json' => is_array($value),
                default => true,
            };

            if (! $valid) {
                $validator->errors()->add('value', 'The value must match the selected value type.');
            }
        }];
    }
}
