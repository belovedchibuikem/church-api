<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SuspendUserRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:191', 'regex:/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->request->all()), ['reason']) as $key) {
                $validator->errors()->add($key, "The {$key} field is not allowed.");
            }

            foreach (array_keys($this->query()) as $key) {
                $validator->errors()->add($key, "The {$key} query parameter is not allowed.");
            }
        }];
    }
}
