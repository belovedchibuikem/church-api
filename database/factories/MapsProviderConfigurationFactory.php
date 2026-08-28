<?php

namespace Database\Factories;

use App\Maps\MapsProvider;
use App\Models\MapsProviderConfiguration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MapsProviderConfiguration>
 */
class MapsProviderConfigurationFactory extends Factory
{
    protected $model = MapsProviderConfiguration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'active_provider' => MapsProvider::Leaflet,
            'google_api_key' => null,
            'mapbox_access_token' => null,
            'leaflet_tile_url' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            'default_latitude' => 6.5244,
            'default_longitude' => 3.3792,
            'default_zoom' => 12,
            'is_active' => false,
            'configuration_revision' => 1,
        ];
    }

    public function activeGoogle(): static
    {
        return $this->state(fn () => [
            'active_provider' => MapsProvider::Google,
            'google_api_key' => 'test-google-maps-key',
            'is_active' => true,
            'activated_at' => now(),
            'validated_at' => now(),
            'last_validation_status' => 'succeeded',
        ]);
    }

    public function activeLeaflet(): static
    {
        return $this->state(fn () => [
            'active_provider' => MapsProvider::Leaflet,
            'is_active' => true,
            'activated_at' => now(),
            'validated_at' => now(),
            'last_validation_status' => 'succeeded',
        ]);
    }
}
