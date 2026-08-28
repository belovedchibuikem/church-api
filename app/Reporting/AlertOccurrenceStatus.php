<?php

namespace App\Reporting;

enum AlertOccurrenceStatus: string
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';
}
