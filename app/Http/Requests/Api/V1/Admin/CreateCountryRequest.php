<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateCountryRequest extends FormRequest
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
            'iso_code' => ['required', 'string', 'size:2', 'alpha:ascii'],
            'name' => ['required', 'string', 'max:191'],
            'local_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'calling_code' => ['sometimes', 'nullable', 'string', 'max:8', 'regex:/^\+?[0-9]{1,4}$/'],
            'currency_code' => ['sometimes', 'nullable', 'string', 'size:3', 'alpha:ascii'],
            'default_timezone' => ['sometimes', 'nullable', 'timezone:all'],
            'locale' => ['sometimes', 'nullable', 'string', 'max:12', 'regex:/^[a-z]{2}(-[A-Z]{2})?$/'],
        ];
    }
}
