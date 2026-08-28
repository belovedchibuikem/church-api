<?php

namespace App\Models;

use App\Finance\PaymentProvider;
use Database\Factories\PaymentProviderConfigurationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'active_provider',
    'paystack_secret_key',
    'paystack_public_key',
    'paystack_webhook_secret',
    'flutterwave_secret_key',
    'flutterwave_public_key',
    'flutterwave_webhook_secret',
    'stripe_secret_key',
    'stripe_publishable_key',
    'stripe_webhook_secret',
    'allowed_purpose_codes',
    'allowed_currencies',
])]
#[Hidden([
    'paystack_secret_key',
    'paystack_public_key',
    'paystack_webhook_secret',
    'flutterwave_secret_key',
    'flutterwave_public_key',
    'flutterwave_webhook_secret',
    'stripe_secret_key',
    'stripe_publishable_key',
    'stripe_webhook_secret',
])]
class PaymentProviderConfiguration extends Model
{
    /** @use HasFactory<PaymentProviderConfigurationFactory> */
    use HasFactory;

    protected $attributes = [
        'active_provider' => 'paystack',
        'allowed_purpose_codes' => '["giving","event_payment"]',
        'allowed_currencies' => '["NGN"]',
        'is_active' => false,
        'configuration_revision' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active_provider' => PaymentProvider::class,
            'paystack_secret_key' => 'encrypted',
            'paystack_public_key' => 'encrypted',
            'paystack_webhook_secret' => 'encrypted',
            'flutterwave_secret_key' => 'encrypted',
            'flutterwave_public_key' => 'encrypted',
            'flutterwave_webhook_secret' => 'encrypted',
            'stripe_secret_key' => 'encrypted',
            'stripe_publishable_key' => 'encrypted',
            'stripe_webhook_secret' => 'encrypted',
            'allowed_purpose_codes' => 'array',
            'allowed_currencies' => 'array',
            'is_active' => 'boolean',
            'configuration_revision' => 'integer',
            'last_validation_attempted_at' => 'datetime',
            'validated_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    public function credentialsConfigured(): bool
    {
        return match ($this->active_provider) {
            PaymentProvider::Paystack => filled($this->paystack_secret_key) && filled($this->paystack_public_key) && filled($this->paystack_webhook_secret),
            PaymentProvider::Flutterwave => filled($this->flutterwave_secret_key) && filled($this->flutterwave_public_key) && filled($this->flutterwave_webhook_secret),
            PaymentProvider::Stripe => filled($this->stripe_secret_key) && filled($this->stripe_publishable_key) && filled($this->stripe_webhook_secret),
        };
    }
}
