<?php

namespace App\Http\Requests\Api\V1\User;

use Illuminate\Foundation\Http\FormRequest;

class SubmitKcaApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'application_data' => ['required', 'array', 'min:1'],
            'application_data.*' => ['nullable', 'string', 'max:5000'],
            'finalize' => ['sometimes', 'boolean'],
        ];
    }
}
