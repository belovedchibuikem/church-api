<?php

namespace App\Finance\Contracts;

use App\Models\PaymentRefund;

interface RefundService
{
    public function request(PaymentRefund $refund): string;
}
