<?php

namespace App\Finance\Actions;

use App\Models\PaymentProviderConfiguration;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class DeactivatePaymentProviderAction
{
    public function __construct(private readonly RecordAuditEventAction $recordAuditEvent) {}

    public function handle(?User $actor = null): void
    {
        DB::transaction(function () use ($actor): void {
            $configuration = PaymentProviderConfiguration::query()->lockForUpdate()->first();

            if ($configuration === null) {
                return;
            }

            $configuration->forceFill([
                'is_active' => false,
                'activated_at' => null,
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'platform.payments.deactivated',
                actor: $actor,
                targetType: 'payment_provider_configuration',
                targetId: (string) $configuration->getKey(),
                scopeType: 'global',
                scopeId: 'platform',
                metadata: ['active_provider' => $configuration->active_provider->value],
            ));
        });
    }
}
