<?php

namespace App\Http\Resources\Api\V1\Content;

use App\Media\PublicMediaUrl;
use App\Models\ContentItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContentItem */
class ContentItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'meta' => $this->publicMeta(),
            'image_url' => PublicMediaUrl::fromLoaded($this->resource, ['cover', 'thumbnail', 'hero'])
                ?? (is_array($this->meta) ? ($this->meta['image_url'] ?? null) : null),
            'href' => $this->href,
            'sort_order' => $this->sort_order,
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }

    private function publicMeta(): mixed
    {
        $meta = $this->meta;
        if (! is_array($meta)) {
            return $meta;
        }

        unset($meta['image_url'], $meta['file_asset_id']);

        return $meta === [] ? null : $meta;
    }
}
