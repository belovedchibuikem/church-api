<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Press\PressAssetFormat;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePressPublicationAssetRequest extends FormRequest
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
            'file_asset_id' => ['required', 'ulid', 'exists:file_assets,public_id'],
            'asset_format' => ['required', Rule::enum(PressAssetFormat::class)],
            'is_required' => ['sometimes', 'boolean'],
            'label' => ['nullable', 'string', 'max:191'],
            'language_code' => ['nullable', 'string', 'max:35'],
        ];
    }
}
