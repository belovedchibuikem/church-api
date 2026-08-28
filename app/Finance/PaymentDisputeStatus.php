<?php

namespace App\Finance;

enum PaymentDisputeStatus: string
{
    case Opened = 'opened';
    case Won = 'won';
    case Lost = 'lost';
    case Closed = 'closed';
}
