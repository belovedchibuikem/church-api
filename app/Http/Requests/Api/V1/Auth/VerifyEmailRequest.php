<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class VerifyEmailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'min:1'],
            'hash' => ['required', 'string', 'size:40', 'regex:/\A[a-f0-9]{40}\z/'],
            'expires' => ['required', 'integer'],
            'signature' => ['required', 'string', 'size:64', 'regex:/\A[a-f0-9]{64}\z/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'id' => $this->route('id'),
            'hash' => $this->route('hash'),
        ]);
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (array_diff(
                array_keys($this->all()),
                ['id', 'hash', 'expires', 'signature'],
            ) !== []) {
                $validator->errors()->add('request', 'The request contains unsupported fields.');
            }
        }];
    }
}
