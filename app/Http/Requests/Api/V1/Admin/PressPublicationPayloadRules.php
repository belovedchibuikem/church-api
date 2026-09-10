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
            'publish_now' => ['sometimes', 'boolean'],
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

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return [
            'title' => 'title',
            'publisher_name' => 'publisher',
            'language_code' => 'language',
            'format' => 'format',
            'publication_type' => 'publication type',
            'content_file_asset_id' => 'document file',
            'content_source_url' => 'document URL',
            'idempotency_key' => 'save request',
            'type_metadata.speaker' => 'speaker',
            'type_metadata.passage' => 'scripture passage',
            'type_metadata.reflection' => 'reflection',
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'title.required' => 'Enter a publication title.',
            'publisher_name.required' => 'Enter a publisher name.',
            'language_code.required' => 'Enter a language code, for example en.',
            'format.required' => 'Choose a format such as PDF, print, or audio.',
            'format.enum' => 'Choose a valid format such as PDF, print, audio, epub, or video.',
            'format.Illuminate\Validation\Rules\Enum' => 'Choose a valid format such as PDF, print, audio, epub, or video.',
            'publication_type.enum' => 'Choose a valid publication type.',
            'publication_type.Illuminate\Validation\Rules\Enum' => 'Choose a valid publication type.',
            'idempotency_key.required' => 'Please try again. The publication could not be saved.',
            'content_source_url.url' => 'Enter a valid document URL starting with http:// or https://.',
        ];
    }
}
