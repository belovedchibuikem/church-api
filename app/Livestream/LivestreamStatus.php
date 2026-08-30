<?php

namespace App\Livestream;

enum LivestreamStatus: string
{
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Ended = 'ended';
}
