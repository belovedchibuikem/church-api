<?php

namespace App\Maps\Actions;

use App\Maps\MapsProvider;
use App\Models\MapsProviderConfiguration;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ActivateMapsProviderAction
{
    public function __construct(private readonly RecordAuditEventAction $recordAuditEvent) {}

    public function handle(MapsProviderConfiguration $configuration, ?User $actor = null): MapsProviderConfiguration
    {
        if (! $configuration->clientKeyConfigured()) {
            throw new RuntimeException(match ($configuration->active_provider) {
                MapsProvider::Google => 'MAPS_GOOGLE_KEY_MISSING',
                MapsProvider::Mapbox => 'MAPS_MAPBOX_TOKEN_MISSING',
                MapsProvider::Leaflet => 'MAPS_PROVIDER_INVALID',
            });
        }

        return DB::transaction(function () use ($configuration, $actor): MapsProviderConfiguration {
            $locked = MapsProviderConfiguration::query()
                ->whereKey($configuration->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $locked->forceFill([
                'is_active' => true,
                'last_validation_status' => 'succeeded',
                'last_validation_failure_code' => null,
                'last_validation_attempted_at' => now(),
                'validated_at' => now(),
                'activated_at' => now(),
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'platform.maps.activated',
                actor: $actor,
                targetType: 'maps_provider_configuration',
                targetId: (string) $locked->getKey(),
                scopeType: 'global',
                scopeId: 'platform',
                metadata: [
                    'active_provider' => $locked->active_provider->value,
                    'configuration_revision' => $locked->configuration_revision,
                ],
            ));

            return $locked->refresh();
        });
    }
}
