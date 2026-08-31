<?php

namespace App\Http\Resources\Api\V1\Public;

use App\Media\PublicMediaUrl;
use App\Models\Church;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Church */
class ChurchResource extends JsonResource
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
            'location' => [
                'id' => $this->location->public_id,
                'name' => $this->location->name,
                'locality' => $this->location->locality,
                'timezone' => $this->location->timezone,
                'country' => [
                    'id' => $this->location->country->public_id,
                    'code' => $this->location->country->iso_code,
                    'name' => $this->location->country->name,
                ],
                'administrative_unit' => $this->location->administrativeUnit === null ? null : [
                    'id' => $this->location->administrativeUnit->public_id,
                    'name' => $this->location->administrativeUnit->name,
                ],
            ],
            'published_at' => $this->published_at?->toIso8601String(),
            'home_churches' => $this->whenLoaded('homeChurches', function () {
                return $this->homeChurches->map(static fn ($home): array => [
                    'id' => $home->public_id,
                    'name' => $home->name,
                    'status' => $home->status?->value ?? (string) $home->status,
                    'meeting_schedules' => $home->meeting_schedules ?? [],
                ])->values()->all();
            }),
        ];
    }
}
