<?php

namespace App\Church;

enum ChurchGroupMembershipStatus: string
{
    case Active = 'active';
    case Pending = 'pending';
    case Left = 'left';
}
