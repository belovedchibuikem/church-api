<?php

namespace App\Communication;

enum CommunicationDeliveryStatus: string
{
    case Pending = 'pending';
    case Suppressed = 'suppressed';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
