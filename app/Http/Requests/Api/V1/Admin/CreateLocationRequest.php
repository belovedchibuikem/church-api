<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateLocationRequest extends FormRequest
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
            'administrative_unit_id' => ['nullable', 'string', Rule::exists('administrative_units', 'public_id')],
            'name' => ['required', 'string', 'max:191'],
            'address_line_one' => ['nullable', 'string', 'max:191'],
            'address_line_two' => ['nullable', 'string', 'max:191'],
            'locality' => ['nullable', 'string', 'max:191'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'timezone' => ['required', 'string', 'timezone'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ];
    }
}
