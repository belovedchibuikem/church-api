<?php

namespace App\Maps\Actions;

use App\Maps\MapsProvider;
use App\Models\MapsProviderConfiguration;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class ConfigureMapsProviderAction
{
    public function __construct(private readonly RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param  array{
     *     active_provider: string,
     *     google_api_key?: string|null,
     *     mapbox_access_token?: string|null,
     *     leaflet_tile_url?: string|null,
     *     default_latitude?: float|null,
     *     default_longitude?: float|null,
     *     default_zoom?: int|null
     * }  $input
     */
    public function handle(array $input, ?User $actor = null): MapsProviderConfiguration
    {
        $provider = MapsProvider::from($input['active_provider']);

        return DB::transaction(function () use ($input, $provider, $actor): MapsProviderConfiguration {
            $configuration = MapsProviderConfiguration::query()->lockForUpdate()->first()
                ?? new MapsProviderConfiguration;

            if (array_key_exists('google_api_key', $input) && filled($input['google_api_key'])) {
                $configuration->google_api_key = (string) $input['google_api_key'];
            }

            if (array_key_exists('mapbox_access_token', $input) && filled($input['mapbox_access_token'])) {
                $configuration->mapbox_access_token = (string) $input['mapbox_access_token'];
            }

            if (array_key_exists('leaflet_tile_url', $input) && $input['leaflet_tile_url'] !== null) {
                $configuration->leaflet_tile_url = (string) $input['leaflet_tile_url'];
            }

            if (isset($input['default_latitude'])) {
                $configuration->default_latitude = (float) $input['default_latitude'];
            }

            if (isset($input['default_longitude'])) {
                $configuration->default_longitude = (float) $input['default_longitude'];
            }

            if (isset($input['default_zoom'])) {
                $configuration->default_zoom = (int) $input['default_zoom'];
            }

            $configuration->active_provider = $provider;
            $configuration->forceFill([
                'is_active' => false,
                'configuration_revision' => $configuration->exists
                    ? $configuration->configuration_revision + 1
                    : 1,
                'last_validation_status' => null,
                'last_validation_failure_code' => null,
                'last_validation_attempted_at' => null,
                'validated_at' => null,
                'activated_at' => null,
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'platform.maps.configured',
                actor: $actor,
                targetType: 'maps_provider_configuration',
                targetId: (string) $configuration->getKey(),
                scopeType: 'global',
                scopeId: 'platform',
                metadata: [
                    'active_provider' => $provider->value,
                    'configuration_revision' => $configuration->configuration_revision,
                    'google_key_present' => filled($configuration->google_api_key),
                    'mapbox_token_present' => filled($configuration->mapbox_access_token),
                ],
            ));

            return $configuration->refresh();
        });
    }
}
