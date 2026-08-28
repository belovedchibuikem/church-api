<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationResource extends JsonResource
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
            'country' => $this->whenLoaded('country', fn (): array => [
                'id' => $this->country->public_id,
                'iso_code' => $this->country->iso_code,
                'name' => $this->country->name,
            ]),
            'administrative_unit' => $this->whenLoaded('administrativeUnit', fn (): ?array => $this->administrativeUnit === null ? null : [
                'id' => $this->administrativeUnit->public_id,
                'name' => $this->administrativeUnit->name,
            ]),
            'address' => [
                'line_one' => $this->address_line_one,
                'line_two' => $this->address_line_two,
                'locality' => $this->locality,
                'postal_code' => $this->postal_code,
            ],
            'timezone' => $this->timezone,
            'coordinates' => $this->latitude === null ? null : [
                'latitude' => (float) $this->latitude,
                'longitude' => (float) $this->longitude,
            ],
            'created_at' => $this->created_at?->utc()->toIso8601String(),
        ];
    }
}
