<?php

namespace App\Church;

enum ChurchMembershipStatus: string
{
    case Active = 'active';
    case Ended = 'ended';
}
