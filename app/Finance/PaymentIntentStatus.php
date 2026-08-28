<?php

namespace App\Finance;

enum PaymentIntentStatus: string
{
    case PendingProvider = 'pending_provider';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
