<?php

namespace App\Http\Resources\Api\V1\Public;

use App\Media\PublicMediaUrl;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicEventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Location|null $location */
        $location = $this->resource->relationLoaded('location')
            ? $this->resource->getRelation('location')
            : null;

        return [
            'id' => $this->public_id,
            'category' => $this->category_code,
            'name' => $this->name,
            'image_url' => PublicMediaUrl::fromLoaded($this->resource),
            'starts_at' => $this->starts_at->utc()->toIso8601String(),
            'ends_at' => $this->ends_at->utc()->toIso8601String(),
            'location' => $location === null ? null : [
                'id' => $location->public_id,
                'name' => $location->name,
                'locality' => $location->locality,
                'timezone' => $location->timezone,
            ],
            'fee' => $this->fee_amount_minor === null ? null : [
                'amount_minor' => $this->fee_amount_minor,
                'currency' => $this->fee_currency,
            ],
        ];
    }
}
