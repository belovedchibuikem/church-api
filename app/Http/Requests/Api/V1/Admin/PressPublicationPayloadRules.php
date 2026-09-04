<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Press\PressPublicationFormat;
use App\Press\PressPublicationType;
use App\Press\PressPublicationVisibility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

final class PressPublicationPayloadRules
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function rules(bool $creating = true): array
    {
        unset($creating);

        return [
            'title' => ['required', 'string', 'max:191'],
            'publisher_name' => ['required', 'string', 'max:191'],
            'language_code' => ['required', 'string', 'max:16'],
            'format' => ['required', Rule::enum(PressPublicationFormat::class)],
            'publication_type' => ['sometimes', Rule::enum(PressPublicationType::class)],
            'visibility' => ['sometimes', Rule::enum(PressPublicationVisibility::class)],
            'as_draft' => ['sometimes', 'boolean'],
            'featured' => ['sometimes', 'boolean'],
            'slug' => ['nullable', 'string', 'max:191'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'subtitle' => ['nullable', 'string', 'max:191'],
            'edition' => ['nullable', 'string', 'max:100'],
            'publication_date' => ['nullable', 'date_format:Y-m-d'],
            'copyright_year' => ['nullable', 'integer', 'min:1450'],
            'page_count' => ['nullable', 'integer', 'min:1'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'cover_file_asset_id' => ['nullable', 'ulid', 'exists:file_assets,public_id'],
            'content_file_asset_id' => ['nullable', 'ulid', 'exists:file_assets,public_id'],
            'content_source_url' => ['nullable', 'string', 'url', 'max:2048'],
            'price_minor' => ['nullable', 'integer', 'min:0'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'type_metadata' => ['sometimes', 'array'],
            'type_metadata.speaker' => ['nullable', 'string', 'max:191'],
            'type_metadata.preacher' => ['nullable', 'string', 'max:191'],
            'type_metadata.speaker_name' => ['nullable', 'string', 'max:191'],
            'type_metadata.preached_date' => ['nullable', 'date_format:Y-m-d'],
            'type_metadata.body' => ['nullable', 'string', 'max:20000'],
            'type_metadata.reflection' => ['nullable', 'string', 'max:20000'],
            'type_metadata.content' => ['nullable', 'string', 'max:20000'],
            'type_metadata.passage' => ['nullable', 'string', 'max:500'],
            'type_metadata.scripture' => ['nullable', 'string', 'max:500'],
            'type_metadata.session_passage' => ['nullable', 'string', 'max:500'],
            'type_metadata.isbn' => ['prohibited'],
        ];
    }
}
