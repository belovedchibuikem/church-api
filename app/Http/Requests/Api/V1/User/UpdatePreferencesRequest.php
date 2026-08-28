<?php

namespace App\Http\Requests\Api\V1\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferencesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', 'max:35', 'regex:/\A[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*\z/'],
            'timezone' => ['required', 'string', 'timezone:all'],
            'notification_channels' => ['required', 'array', 'max:20'],
            'notification_channels.*' => [
                'required',
                'string',
                'max:50',
                'distinct:strict',
                'regex:/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/',
            ],
        ];
    }
}
