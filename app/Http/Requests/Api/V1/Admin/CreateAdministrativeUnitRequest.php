<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAdministrativeUnitRequest extends FormRequest
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
            'country_id' => ['required', 'string', Rule::exists('countries', 'public_id')],
            'administrative_level_id' => ['required', 'string', Rule::exists('administrative_levels', 'public_id')],
            'parent_id' => ['nullable', 'string', Rule::exists('administrative_units', 'public_id')],
            'name' => ['required', 'string', 'max:191'],
            'reference_code' => ['nullable', 'string', 'max:100', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._:-]*\z/'],
        ];
    }
}
