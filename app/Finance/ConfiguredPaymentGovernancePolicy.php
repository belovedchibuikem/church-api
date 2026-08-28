<?php

namespace App\Finance;

use App\Finance\Contracts\PaymentGovernancePolicy;
use App\Models\PaymentTransaction;
use App\Models\Person;
use App\Models\User;

/**
 * Env-driven governance for OD-009/010 until a regional PSP is selected.
 *
 * Modes:
 * - deny (default)
 * - allow_local / allow_configured — allow listed purpose codes + currencies
 */
class ConfiguredPaymentGovernancePolicy implements PaymentGovernancePolicy
{
    public function allowsPaymentIntent(string $purposeCode, string $currency, ?Person $payer): bool
    {
        $mode = strtolower((string) config('finance.governance_mode', 'deny'));
        if (! in_array($mode, ['allow_local', 'allow_configured'], true)) {
            return false;
        }

        $purposes = config('finance.allowed_purpose_codes', ['giving']);
        $currencies = config('finance.allowed_currencies', ['NGN']);

        return in_array($purposeCode, $purposes, true)
            && in_array(strtoupper($currency), $currencies, true)
            && $payer !== null;
    }

    public function allowsRefund(PaymentTransaction $transaction, int $amountMinor, ?User $actor): bool
    {
        $mode = strtolower((string) config('finance.governance_mode', 'deny'));
        if (! in_array($mode, ['allow_local', 'allow_configured'], true)) {
            return false;
        }

        return $amountMinor > 0 && $actor !== null;
    }
}
