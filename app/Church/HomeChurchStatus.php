<?php

namespace App\Church;

enum HomeChurchStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';
}
