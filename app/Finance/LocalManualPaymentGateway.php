<?php

namespace App\Finance;

use App\Finance\Contracts\PaymentGateway;
use App\Models\PaymentIntent;
use Illuminate\Support\Str;

/**
 * Local/manual PSP stand-in. Does not call Stripe/Paystack; returns instructions
 * clients can show, plus a reference used by CompleteLocalGivingAction.
 */
class LocalManualPaymentGateway implements PaymentGateway
{
    public function providerCode(): string
    {
        return 'local_manual';
    }

    public function initiate(PaymentIntent $paymentIntent): array
    {
        $reference = 'local_'.Str::lower((string) $paymentIntent->public_id);
        $base = rtrim((string) config('finance.local_manual.checkout_base_url'), '/');

        return [
            'provider_reference' => $reference,
            'client_payload' => [
                'provider' => $this->providerCode(),
                'checkout_mode' => 'manual_confirm',
                'checkout_url' => $base.'?intent='.$paymentIntent->public_id,
                'instructions' => 'Confirm this giving intent from the app after the member acknowledges payment (local/manual gateway).',
                'amount_minor' => $paymentIntent->amount_minor,
                'currency' => $paymentIntent->currency,
                'intent_id' => $paymentIntent->public_id,
            ],
        ];
    }
}
