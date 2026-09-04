<?php

namespace App\Http\Resources\Api\V1\Public;

use App\Media\PublicMediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PressPublicationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'publisher' => $this->publisher_name,
            'edition' => $this->edition,
            'publication_date' => $this->publication_date?->toDateString(),
            'copyright_year' => $this->copyright_year,
            'language' => $this->language_code,
            'page_count' => $this->page_count,
            'category' => $this->category,
            'description' => $this->description,
            'image_url' => PublicMediaUrl::fromLoaded($this->resource)
                ?? PublicMediaUrl::forAsset($this->relationLoaded('coverFileAsset') ? $this->coverFileAsset : null),
            'isbn' => $this->isbn,
            'isbn_type' => $this->isbn_type?->value,
            'format' => $this->format->value,
            'publication_type' => $this->publicationType()->value,
            'summary' => $this->summary,
            'slug' => $this->slug,
            'type_metadata' => is_array($this->type_metadata) ? $this->type_metadata : [],
            'content_source_url' => $this->content_source_url,
            'has_download' => $this->content_file_asset_id !== null || (is_string($this->content_source_url) && $this->content_source_url !== ''),
            'availability' => $this->availability->value,
            'published_at' => $this->published_at?->utc()->toIso8601String(),
            'translations' => PressTranslationResource::collection($this->whenLoaded('translations')),
        ];
    }
}
