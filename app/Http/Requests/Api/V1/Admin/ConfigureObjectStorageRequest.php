<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConfigureObjectStorageRequest extends FormRequest
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
            'access_key_id' => ['required', 'string', 'max:255'],
            'secret_access_key' => ['required', 'string', 'max:2048'],
            'region' => ['required', 'string', 'max:100', 'regex:/\A[a-z0-9][a-z0-9-]*\z/'],
            'bucket' => ['required', 'string', 'between:3,191', 'regex:/\A[a-z0-9][a-z0-9.-]*[a-z0-9]\z/'],
            'endpoint' => ['nullable', 'url:https', 'max:2048'],
            'url' => ['nullable', 'url:https', 'max:2048'],
            'root_prefix' => ['nullable', 'string', 'max:1024', 'not_regex:/[\x00-\x1F\x7F]/'],
            'use_path_style_endpoint' => ['sometimes', 'boolean'],
        ];
    }
}
