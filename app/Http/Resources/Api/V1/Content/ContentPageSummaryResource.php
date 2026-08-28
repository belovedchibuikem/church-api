<?php

namespace App\Http\Resources\Api\V1\Content;

use App\Models\ContentPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContentPage */
class ContentPageSummaryResource extends JsonResource
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
            'locale' => $this->locale,
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
