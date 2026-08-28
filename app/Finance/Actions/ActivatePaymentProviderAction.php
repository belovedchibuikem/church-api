<?php

namespace App\Finance\Actions;

use App\Models\PaymentProviderConfiguration;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ActivatePaymentProviderAction
{
    public function __construct(private readonly RecordAuditEventAction $recordAuditEvent) {}

    public function handle(PaymentProviderConfiguration $configuration, ?User $actor = null): PaymentProviderConfiguration
    {
        if (! $configuration->credentialsConfigured()) {
            throw new RuntimeException('PAYMENT_PROVIDER_CREDENTIALS_MISSING');
        }

        return DB::transaction(function () use ($configuration, $actor): PaymentProviderConfiguration {
            $locked = PaymentProviderConfiguration::query()
                ->whereKey($configuration->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $locked->forceFill([
                'is_active' => true,
                'last_validation_status' => 'credentials_verified',
                'last_validation_failure_code' => null,
                'last_validation_attempted_at' => now(),
                'validated_at' => now(),
                'activated_at' => now(),
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'platform.payments.activated',
                actor: $actor,
                targetType: 'payment_provider_configuration',
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
