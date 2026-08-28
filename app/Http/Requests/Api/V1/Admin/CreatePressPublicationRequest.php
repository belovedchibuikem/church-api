<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Press\PressPublicationFormat;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePressPublicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key') ?? $this->input('idempotency_key')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'between:8,191'],
            'title' => ['required', 'string', 'max:191'],
            'publisher_name' => ['required', 'string', 'max:191'],
            'language_code' => ['required', 'string', 'max:16'],
            'format' => ['required', Rule::enum(PressPublicationFormat::class)],
            'subtitle' => ['nullable', 'string', 'max:191'],
            'edition' => ['nullable', 'string', 'max:100'],
            'publication_date' => ['nullable', 'date_format:Y-m-d'],
            'copyright_year' => ['nullable', 'integer', 'min:1450'],
            'page_count' => ['nullable', 'integer', 'min:1'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'cover_file_asset_id' => ['nullable', 'ulid', 'exists:file_assets,public_id'],
            'content_file_asset_id' => ['nullable', 'ulid', 'exists:file_assets,public_id'],
            'price_minor' => ['nullable', 'integer', 'min:0'],
            'currency_code' => ['nullable', 'string', 'size:3'],
        ];
    }
}
