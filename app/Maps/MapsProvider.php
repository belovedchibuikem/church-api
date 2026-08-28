<?php

namespace App\Maps;

enum MapsProvider: string
{
    case Google = 'google';
    case Mapbox = 'mapbox';
    case Leaflet = 'leaflet';

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google Maps',
            self::Mapbox => 'Mapbox',
            self::Leaflet => 'Leaflet (OpenStreetMap)',
        };
    }

    public function requiresClientKey(): bool
    {
        return $this !== self::Leaflet;
    }
}
