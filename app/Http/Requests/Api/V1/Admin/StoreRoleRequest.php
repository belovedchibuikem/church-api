<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:191', 'regex:/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', Rule::unique('roles', 'code')],
            'name' => ['required', 'string', 'max:191'],
        ];
    }
}
