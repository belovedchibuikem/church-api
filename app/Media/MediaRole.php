<?php

namespace App\Media;

enum MediaRole: string
{
    case Cover = 'cover';
    case Hero = 'hero';
    case Thumbnail = 'thumbnail';
    case Avatar = 'avatar';
    case Logo = 'logo';
    case Document = 'document';
}
