<?php

namespace App\Press;

enum PressPublicationAvailability: string
{
    case Unavailable = 'unavailable';
    case Available = 'available';
}
