<?php

namespace Database\Factories;

use App\Finance\PaymentRefundStatus;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentRefund>
 */
class PaymentRefundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['payment_transaction_id' => PaymentTransaction::factory(), 'requested_by_user_id' => null, 'amount_minor' => 500, 'currency' => 'USD', 'reason_code' => 'requested_by_payer', 'status' => PaymentRefundStatus::Requested, 'idempotency_scope_hash' => hash('sha256', Str::uuid()->toString()), 'payload_fingerprint' => hash('sha256', Str::uuid()->toString()), 'requested_at' => now()];
    }
}
