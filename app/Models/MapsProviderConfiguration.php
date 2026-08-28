<?php

namespace App\Models;

use App\Maps\MapsProvider;
use Database\Factories\MapsProviderConfigurationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'active_provider',
    'google_api_key',
    'mapbox_access_token',
    'leaflet_tile_url',
    'default_latitude',
    'default_longitude',
    'default_zoom',
])]
#[Hidden(['google_api_key', 'mapbox_access_token'])]
class MapsProviderConfiguration extends Model
{
    /** @use HasFactory<MapsProviderConfigurationFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'active_provider' => 'leaflet',
        'leaflet_tile_url' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        'default_latitude' => 6.5244000,
        'default_longitude' => 3.3792000,
        'default_zoom' => 12,
        'is_active' => false,
        'configuration_revision' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active_provider' => MapsProvider::class,
            'google_api_key' => 'encrypted',
            'mapbox_access_token' => 'encrypted',
            'default_latitude' => 'float',
            'default_longitude' => 'float',
            'default_zoom' => 'integer',
            'is_active' => 'boolean',
            'configuration_revision' => 'integer',
            'last_validation_attempted_at' => 'datetime',
            'validated_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    public function clientKeyConfigured(): bool
    {
        return match ($this->active_provider) {
            MapsProvider::Google => filled($this->google_api_key),
            MapsProvider::Mapbox => filled($this->mapbox_access_token),
            MapsProvider::Leaflet => true,
        };
    }

    public function clientKey(): ?string
    {
        return match ($this->active_provider) {
            MapsProvider::Google => $this->google_api_key,
            MapsProvider::Mapbox => $this->mapbox_access_token,
            MapsProvider::Leaflet => null,
        };
    }
}
