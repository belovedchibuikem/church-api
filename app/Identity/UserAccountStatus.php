<?php

namespace App\Identity;

enum UserAccountStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
