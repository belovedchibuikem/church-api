<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MapsProviderConfigurationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'configured' => true,
            'active' => $this->is_active,
            'active_provider' => $this->active_provider->value,
            'providers' => [
                'google' => [
                    'label' => 'Google Maps',
                    'requires_key' => true,
                    'key_configured' => filled($this->google_api_key),
                ],
                'mapbox' => [
                    'label' => 'Mapbox',
                    'requires_key' => true,
                    'key_configured' => filled($this->mapbox_access_token),
                ],
                'leaflet' => [
                    'label' => 'Leaflet (OpenStreetMap)',
                    'requires_key' => false,
                    'key_configured' => true,
                    'tile_url' => $this->leaflet_tile_url,
                ],
            ],
            'default_center' => [
                'latitude' => $this->default_latitude,
                'longitude' => $this->default_longitude,
            ],
            'default_zoom' => $this->default_zoom,
            'configuration_revision' => $this->configuration_revision,
            'validation' => [
                'status' => $this->last_validation_status,
                'failure_code' => $this->last_validation_failure_code,
                'attempted_at' => $this->last_validation_attempted_at?->utc()->toIso8601String(),
                'validated_at' => $this->validated_at?->utc()->toIso8601String(),
            ],
            'activated_at' => $this->activated_at?->utc()->toIso8601String(),
        ];
    }
}
