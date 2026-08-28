<?php

namespace Database\Factories;

use App\Models\PaymentIntent;
use App\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentTransaction>
 */
class PaymentTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['payment_intent_id' => PaymentIntent::factory(), 'provider_code' => 'test_gateway', 'provider_event_hash' => hash('sha256', Str::uuid()->toString()), 'provider_reference_hash' => hash('sha256', Str::uuid()->toString()), 'amount_minor' => 1000, 'currency' => 'USD', 'occurred_at' => now()];
    }
}
