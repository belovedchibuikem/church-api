<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Public\PublicMapsConfigurationResource;
use App\Models\Church;
use App\Models\HomeChurch;
use App\Models\MapsProviderConfiguration;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicMapsController extends Controller
{
    public function configuration(Request $request): JsonResponse
    {
        $configuration = MapsProviderConfiguration::query()->where('is_active', true)->first();

        if ($configuration === null) {
            return ApiResponse::success($request, [
                'active' => false,
                'provider' => 'leaflet',
                'client_api_key' => null,
                'tile_url' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                'default_center' => ['latitude' => 6.5244, 'longitude' => 3.3792],
                'default_zoom' => 12,
                'features' => [
                    'interactive' => true,
                    'geolocation' => true,
                    'directions' => true,
                    'markers' => true,
                ],
                'fallback_mode' => 'leaflet_osm',
            ]);
        }

        return ApiResponse::success(
            $request,
            (new PublicMapsConfigurationResource($configuration))->resolve($request),
        );
    }

    public function places(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', 'in:church,home_church,all'],
            'near_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'near_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'between:0.1,500'],
            'limit' => ['nullable', 'integer', 'between:1,200'],
        ]);

        $type = $validated['type'] ?? 'all';
        $limit = (int) ($validated['limit'] ?? 100);
        $nearLat = isset($validated['near_lat']) ? (float) $validated['near_lat'] : null;
        $nearLng = isset($validated['near_lng']) ? (float) $validated['near_lng'] : null;
        $radiusKm = isset($validated['radius_km']) ? (float) $validated['radius_km'] : null;

        $places = collect();

        if ($type === 'all' || $type === 'church') {
            $churches = Church::query()
                ->whereNotNull('published_at')
                ->with(['location.country'])
                ->whereHas('location', fn ($query) => $query->whereNotNull('latitude')->whereNotNull('longitude'))
                ->limit($limit)
                ->get()
                ->map(fn (Church $church) => $this->placeFrom(
                    id: $church->public_id,
                    name: $church->name,
                    type: 'church',
                    location: $church->location,
                    href: '/churches/'.$church->public_id,
                ));
            $places = $places->concat($churches);
        }

        if ($type === 'all' || $type === 'home_church') {
            $homes = HomeChurch::query()
                ->where('status', 'active')
                ->with(['location.country'])
                ->whereHas('location', fn ($query) => $query->whereNotNull('latitude')->whereNotNull('longitude'))
                ->limit($limit)
                ->get()
                ->map(fn (HomeChurch $home) => $this->placeFrom(
                    id: $home->public_id,
                    name: $home->name,
                    type: 'home_church',
                    location: $home->location,
                    href: '/home-churches/'.$home->public_id,
                ));
            $places = $places->concat($homes);
        }

        if ($nearLat !== null && $nearLng !== null) {
            $places = $places->map(function (array $place) use ($nearLat, $nearLng): array {
                $place['distance_km'] = $this->haversineKm(
                    $nearLat,
                    $nearLng,
                    $place['coordinates']['latitude'],
                    $place['coordinates']['longitude'],
                );

                return $place;
            });

            if ($radiusKm !== null) {
                $places = $places->filter(fn (array $place) => ($place['distance_km'] ?? PHP_FLOAT_MAX) <= $radiusKm);
            }

            $places = $places->sortBy('distance_km')->values();
        }

        return ApiResponse::success($request, $places->take($limit)->values()->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function placeFrom(string $id, string $name, string $type, mixed $location, string $href): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'type' => $type,
            'address' => trim(implode(', ', array_filter([
                $location->name,
                $location->locality,
                $location->country?->name,
            ]))),
            'coordinates' => [
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
            ],
            'href' => $href,
        ];
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }
}
