<?php

namespace App\Http\Resources\Api\V1\Content;

use App\Media\PublicMediaUrl;
use App\Models\ContentPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContentPage */
class ContentPageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'body' => $this->body,
            'locale' => $this->locale,
            'image_url' => PublicMediaUrl::fromLoaded($this->resource, ['hero', 'cover']),
            'published_at' => $this->published_at?->toIso8601String(),
            'items' => ContentItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
