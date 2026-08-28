<?php

namespace Database\Factories;

use App\Finance\PaymentReconciliationStatus;
use App\Models\PaymentReconciliation;
use App\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentReconciliation>
 */
class PaymentReconciliationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['payment_transaction_id' => PaymentTransaction::factory(), 'status' => PaymentReconciliationStatus::Matched, 'reason_code' => 'amount_currency_matched', 'reconciled_at' => now()];
    }
}
