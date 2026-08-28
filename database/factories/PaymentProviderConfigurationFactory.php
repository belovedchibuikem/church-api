<?php

namespace Database\Factories;

use App\Finance\PaymentProvider;
use App\Models\PaymentProviderConfiguration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentProviderConfiguration>
 */
class PaymentProviderConfigurationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'active_provider' => PaymentProvider::Paystack,
            'paystack_secret_key' => 'sk_test_'.$this->faker->uuid(),
            'paystack_public_key' => 'pk_test_'.$this->faker->uuid(),
            'paystack_webhook_secret' => $this->faker->uuid(),
            'allowed_purpose_codes' => ['giving', 'event_payment'],
            'allowed_currencies' => ['NGN'],
            'is_active' => false,
            'configuration_revision' => 1,
        ];
    }
}
