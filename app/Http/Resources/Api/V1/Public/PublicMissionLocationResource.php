<?php

namespace App\Http\Resources\Api\V1\Public;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Location */
class PublicMissionLocationResource extends JsonResource
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
            'locality' => $this->locality,
            'timezone' => $this->timezone,
            'coordinates' => $this->latitude !== null && $this->longitude !== null
                ? [
                    'latitude' => (float) $this->latitude,
                    'longitude' => (float) $this->longitude,
                ]
                : null,
            'country' => $this->whenLoaded('country', fn (): array => [
                'code' => $this->country->iso_code,
                'name' => $this->country->name,
            ]),
        ];
    }
}
