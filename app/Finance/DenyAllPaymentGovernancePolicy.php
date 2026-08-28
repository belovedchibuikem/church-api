<?php

namespace App\Finance;

use App\Finance\Contracts\PaymentGovernancePolicy;
use App\Models\PaymentTransaction;
use App\Models\Person;
use App\Models\User;

class DenyAllPaymentGovernancePolicy implements PaymentGovernancePolicy
{
    public function allowsPaymentIntent(string $purposeCode, string $currency, ?Person $payer): bool
    {
        return false;
    }

    public function allowsRefund(PaymentTransaction $transaction, int $amountMinor, ?User $actor): bool
    {
        return false;
    }
}
