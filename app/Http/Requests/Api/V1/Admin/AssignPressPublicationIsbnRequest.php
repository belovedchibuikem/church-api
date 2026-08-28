<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AssignPressPublicationIsbnRequest extends FormRequest
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
            'isbn' => ['required', 'string', 'max:32'],
            'reason_code' => ['required', 'string', 'max:100', 'regex:/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/'],
        ];
    }
}
