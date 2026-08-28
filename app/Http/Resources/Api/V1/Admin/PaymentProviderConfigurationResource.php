<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentProviderConfigurationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'configured' => true,
            'active' => $this->is_active,
            'active_provider' => $this->active_provider->value,
            'providers' => [
                'paystack' => [
                    'label' => 'Paystack',
                    'credentials_configured' => filled($this->paystack_secret_key)
                        && filled($this->paystack_public_key)
                        && filled($this->paystack_webhook_secret),
                ],
                'flutterwave' => [
                    'label' => 'Flutterwave',
                    'credentials_configured' => filled($this->flutterwave_secret_key)
                        && filled($this->flutterwave_public_key)
                        && filled($this->flutterwave_webhook_secret),
                ],
                'stripe' => [
                    'label' => 'Stripe',
                    'credentials_configured' => filled($this->stripe_secret_key)
                        && filled($this->stripe_publishable_key)
                        && filled($this->stripe_webhook_secret),
                ],
            ],
            'allowed_purpose_codes' => $this->allowed_purpose_codes,
            'allowed_currencies' => $this->allowed_currencies,
            'configuration_revision' => $this->configuration_revision,
            'validation' => [
                'status' => $this->last_validation_status,
                'failure_code' => $this->last_validation_failure_code,
                'attempted_at' => $this->last_validation_attempted_at?->utc()->toIso8601String(),
                'validated_at' => $this->validated_at?->utc()->toIso8601String(),
            ],
            'activated_at' => $this->activated_at?->utc()->toIso8601String(),
        ];
    }
}
