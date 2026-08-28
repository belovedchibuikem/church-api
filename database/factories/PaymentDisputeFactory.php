<?php

namespace Database\Factories;

use App\Finance\PaymentDisputeStatus;
use App\Models\PaymentDispute;
use App\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentDispute>
 */
class PaymentDisputeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['payment_transaction_id' => PaymentTransaction::factory(), 'provider_event_hash' => hash('sha256', Str::uuid()->toString()), 'dispute_case_hash' => hash('sha256', Str::uuid()->toString()), 'status' => PaymentDisputeStatus::Opened, 'reason_code' => 'provider_reported', 'amount_minor' => 1000, 'occurred_at' => now()];
    }
}
