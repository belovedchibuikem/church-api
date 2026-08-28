<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MfaChallengeRequest extends FormRequest
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
            'method_id' => ['nullable', 'string', 'ulid'],
            'code' => ['nullable', 'string', 'regex:/\A[0-9]{6}\z/', 'required_without:recovery_code', 'prohibited_with:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'max:255', 'required_without:code', 'prohibited_with:code'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['method_id', 'code', 'recovery_code']) as $key) {
                $validator->errors()->add($key, 'This field is not supported.');
            }
        }];
    }
}
