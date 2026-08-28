<?php

namespace App\Finance;

enum PaymentReconciliationStatus: string
{
    case Matched = 'matched';
    case Mismatch = 'mismatch';
}
