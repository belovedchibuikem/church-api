<?php

namespace App\Http\Resources\Api\V1\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicMapsConfigurationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $provider = $this->active_provider;

        return [
            'active' => $this->is_active,
            'provider' => $provider->value,
            'client_api_key' => $this->is_active ? $this->clientKey() : null,
            'tile_url' => $provider->value === 'leaflet'
                ? ($this->leaflet_tile_url ?: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png')
                : null,
            'default_center' => [
                'latitude' => (float) $this->default_latitude,
                'longitude' => (float) $this->default_longitude,
            ],
            'default_zoom' => (int) $this->default_zoom,
            'features' => [
                'interactive' => true,
                'geolocation' => true,
                'directions' => true,
                'markers' => true,
            ],
        ];
    }
}
