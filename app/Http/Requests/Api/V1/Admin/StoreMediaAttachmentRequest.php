<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Media\MediaAttachableType;
use App\Media\MediaRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMediaAttachmentRequest extends FormRequest
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
            'attachable_type' => ['required', 'string', Rule::in(MediaAttachableType::aliases())],
            'attachable_id' => ['required', 'ulid'],
            'file_asset_id' => ['required', 'ulid', 'exists:file_assets,public_id'],
            'role' => ['required', Rule::enum(MediaRole::class)],
        ];
    }
}
