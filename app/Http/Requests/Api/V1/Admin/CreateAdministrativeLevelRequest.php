<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateAdministrativeLevelRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:100', 'regex:/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/'],
            'name' => ['required', 'string', 'max:191'],
            'sort_order' => ['required', 'integer', 'between:1,65535'],
        ];
    }
}
