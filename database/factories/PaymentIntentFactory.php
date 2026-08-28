<?php

namespace Database\Factories;

use App\Finance\PaymentIntentStatus;
use App\Models\PaymentIntent;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentIntent>
 */
class PaymentIntentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['payer_person_id' => Person::factory(), 'event_registration_id' => null, 'purpose_code' => 'donation', 'amount_minor' => 1000, 'currency' => 'USD', 'status' => PaymentIntentStatus::PendingProvider, 'idempotency_scope_hash' => hash('sha256', Str::uuid()->toString()), 'payload_fingerprint' => hash('sha256', Str::uuid()->toString()), 'expires_at' => null, 'succeeded_at' => null];
    }
}
