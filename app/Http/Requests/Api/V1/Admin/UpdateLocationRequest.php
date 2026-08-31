<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
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
