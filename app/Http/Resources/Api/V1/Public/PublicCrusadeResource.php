<?php

namespace App\Http\Resources\Api\V1\Public;

use App\Media\PublicMediaUrl;
use App\Models\Crusade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Crusade */
class PublicCrusadeResource extends JsonResource
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
            'name' => $this->name,
            'image_url' => PublicMediaUrl::fromLoaded($this->resource),
            'starts_at' => $this->starts_at?->utc()->toIso8601String(),
            'ends_at' => $this->ends_at?->utc()->toIso8601String(),
            'location' => $this->whenLoaded(
                'location',
                fn (): ?PublicMissionLocationResource => $this->location === null
                    ? null
                    : new PublicMissionLocationResource($this->location),
            ),
        ];
    }
}
