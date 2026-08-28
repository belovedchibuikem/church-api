<?php

namespace App\Maps\Actions;

use App\Models\MapsProviderConfiguration;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class DeactivateMapsProviderAction
{
    public function __construct(private readonly RecordAuditEventAction $recordAuditEvent) {}

    public function handle(?User $actor = null): void
    {
        DB::transaction(function () use ($actor): void {
            $configuration = MapsProviderConfiguration::query()->lockForUpdate()->first();

            if ($configuration === null) {
                return;
            }

            $configuration->forceFill([
                'is_active' => false,
                'activated_at' => null,
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'platform.maps.deactivated',
                actor: $actor,
                targetType: 'maps_provider_configuration',
                targetId: (string) $configuration->getKey(),
                scopeType: 'global',
                scopeId: 'platform',
                metadata: [
                    'active_provider' => $configuration->active_provider->value,
                ],
            ));
        });
    }
}
