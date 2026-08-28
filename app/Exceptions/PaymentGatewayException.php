<?php

namespace App\Exceptions;

use RuntimeException;

class PaymentGatewayException extends RuntimeException
{
    public function __construct(
        public readonly string $failureCode = 'PAYMENT_GATEWAY_FAILED',
        string $message = 'The payment provider could not start checkout.',
    ) {
        parent::__construct($message);
    }
}
