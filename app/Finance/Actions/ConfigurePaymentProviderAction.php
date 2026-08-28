<?php

namespace App\Finance\Actions;

use App\Finance\PaymentProvider;
use App\Models\PaymentProviderConfiguration;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class ConfigurePaymentProviderAction
{
    public function __construct(private readonly RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input, ?User $actor = null): PaymentProviderConfiguration
    {
        $provider = PaymentProvider::from((string) $input['active_provider']);

        return DB::transaction(function () use ($input, $provider, $actor): PaymentProviderConfiguration {
            $configuration = PaymentProviderConfiguration::query()->lockForUpdate()->first()
                ?? new PaymentProviderConfiguration;

            foreach ([
                'paystack_secret_key',
                'paystack_public_key',
                'paystack_webhook_secret',
                'flutterwave_secret_key',
                'flutterwave_public_key',
                'flutterwave_webhook_secret',
                'stripe_secret_key',
                'stripe_publishable_key',
                'stripe_webhook_secret',
            ] as $credential) {
                if (array_key_exists($credential, $input) && filled($input[$credential])) {
                    $configuration->{$credential} = (string) $input[$credential];
                }
            }

            $configuration->active_provider = $provider;

            if (array_key_exists('allowed_purpose_codes', $input)) {
                $configuration->allowed_purpose_codes = array_values($input['allowed_purpose_codes']);
            }

            if (array_key_exists('allowed_currencies', $input)) {
                $configuration->allowed_currencies = array_values($input['allowed_currencies']);
            }

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
                action: 'platform.payments.configured',
                actor: $actor,
                targetType: 'payment_provider_configuration',
                targetId: (string) $configuration->getKey(),
                scopeType: 'global',
                scopeId: 'platform',
                metadata: [
                    'active_provider' => $provider->value,
                    'configuration_revision' => $configuration->configuration_revision,
                    'credentials_configured' => $configuration->credentialsConfigured(),
                    'allowed_purpose_codes' => $configuration->allowed_purpose_codes,
                    'allowed_currencies' => $configuration->allowed_currencies,
                ],
            ));

            return $configuration->refresh();
        });
    }
}
