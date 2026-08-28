<?php

namespace App\Finance;

use App\Finance\Contracts\PaymentGovernancePolicy;
use App\Models\PaymentTransaction;
use App\Models\Person;
use App\Models\User;

class DatabasePaymentGovernancePolicy implements PaymentGovernancePolicy
{
    public function __construct(
        private readonly ResolvesActivePaymentConfiguration $configurations,
        private readonly PaymentGovernancePolicy $fallback,
    ) {}

    public function allowsPaymentIntent(string $purposeCode, string $currency, ?Person $payer): bool
    {
        $configuration = $this->configurations->active();

        if ($configuration !== null) {
            $purposes = array_map('strval', $configuration->allowed_purpose_codes ?? []);
            $currencies = array_map(
                static fn (mixed $code): string => strtoupper((string) $code),
                $configuration->allowed_currencies ?? [],
            );

            return in_array($purposeCode, $purposes, true)
                && in_array(strtoupper($currency), $currencies, true)
                && $payer !== null;
        }

        return $this->fallback->allowsPaymentIntent($purposeCode, $currency, $payer);
    }

    public function allowsRefund(PaymentTransaction $transaction, int $amountMinor, ?User $actor): bool
    {
        if ($this->configurations->active() !== null) {
            return $amountMinor > 0 && $actor !== null;
        }

        return $this->fallback->allowsRefund($transaction, $amountMinor, $actor);
    }
}
