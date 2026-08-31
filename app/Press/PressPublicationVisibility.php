<?php

namespace App\Press;

enum PressPublicationVisibility: string
{
    case Public = 'public';
    case Members = 'members';
    case Private = 'private';

    public function isPublicCatalogue(): bool
    {
        return $this === self::Public;
    }
}
