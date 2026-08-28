<?php

namespace Database\Factories;

use App\Models\PaymentReceipt;
use App\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentReceipt>
 */
class PaymentReceiptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['payment_transaction_id' => PaymentTransaction::factory(), 'receipt_number' => 'R-'.Str::ulid(), 'issued_at' => now()];
    }
}
