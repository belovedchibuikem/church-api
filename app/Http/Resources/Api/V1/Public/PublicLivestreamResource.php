<?php

namespace App\Http\Resources\Api\V1\Public;

use App\Support\Livestream\YoutubeLivestreamUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Livestream */
class PublicLivestreamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'host_name' => $this->host_name,
            'provider' => $this->provider,
            'external_id' => $this->external_id,
            'watch_url' => $this->watch_url,
            'embed_url' => $this->embed_url,
            'thumbnail_url' => YoutubeLivestreamUrl::thumbnailUrl((string) $this->external_id),
            'status' => $this->status?->value ?? (string) $this->status,
            'viewer_count' => $this->viewer_count,
            'reaction_count' => $this->reaction_count,
            'starts_at' => $this->starts_at?->utc()->toIso8601String(),
            'ended_at' => $this->ended_at?->utc()->toIso8601String(),
            'church_id' => $this->church?->public_id,
            'church_name' => $this->church?->name,
        ];
    }
}
