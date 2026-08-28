<?php

namespace App\Safeguarding;

enum MinorStatus: string
{
    case Unknown = 'unknown';
    case ConfirmedMinor = 'confirmed_minor';
    case ConfirmedAdult = 'confirmed_adult';
}
