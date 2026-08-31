<?php

namespace App\Church;

enum MembershipJoinIntent: string
{
    case Admin = 'admin';
    case Conventional = 'conventional';
    case HomeChurch = 'home_church';
}
