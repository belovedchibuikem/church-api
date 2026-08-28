<?php

namespace App\Communication;

enum CommunicationBroadcastStatus: string
{
    case Draft = 'draft';
    case Prepared = 'prepared';
    case Cancelled = 'cancelled';
}
