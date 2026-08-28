<?php

namespace App\Finance\Contracts;

use App\Models\PaymentTransaction;
use App\Models\Person;
use App\Models\User;

interface PaymentGovernancePolicy
{
    public function allowsPaymentIntent(string $purposeCode, string $currency, ?Person $payer): bool;

    public function allowsRefund(PaymentTransaction $transaction, int $amountMinor, ?User $actor): bool;
}
