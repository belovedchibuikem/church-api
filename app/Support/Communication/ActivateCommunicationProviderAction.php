<?php

namespace App\Support\Communication;

use App\Models\CommunicationProviderConfiguration;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ActivateCommunicationProviderAction
{
    public function __construct(private readonly RecordAuditEventAction $recordAuditEvent) {}

    public function handle(CommunicationProviderConfiguration $configuration, ?User $actor = null): CommunicationProviderConfiguration
    {
        $configured = collect(['email', 'sms', 'whatsapp', 'push'])
            ->contains(fn (string $channel): bool => $configuration->channelConfigured($channel));

        if (! $configured) {
            throw new RuntimeException('COMMUNICATION_PROVIDER_CREDENTIALS_MISSING');
        }

        return DB::transaction(function () use ($configuration, $actor): CommunicationProviderConfiguration {
            $locked = CommunicationProviderConfiguration::query()
                ->whereKey($configuration->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $locked->forceFill([
                'is_active' => true,
                'activated_at' => now(),
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'platform.communications.activated',
                actor: $actor,
                targetType: 'communication_provider_configuration',
                targetId: (string) $locked->getKey(),
                scopeType: 'global',
                scopeId: 'platform',
                metadata: ['configuration_revision' => $locked->configuration_revision],
            ));

            return $locked->refresh();
        });
    }
}
