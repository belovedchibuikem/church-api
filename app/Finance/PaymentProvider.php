<?php

namespace App\Finance;

enum PaymentProvider: string
{
    case Paystack = 'paystack';
    case Flutterwave = 'flutterwave';
    case Stripe = 'stripe';

    public function label(): string
    {
        return match ($this) {
            self::Paystack => 'Paystack',
            self::Flutterwave => 'Flutterwave',
            self::Stripe => 'Stripe',
        };
    }
}
