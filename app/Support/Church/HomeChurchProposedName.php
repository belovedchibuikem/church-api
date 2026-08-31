<?php

namespace App\Support\Church;

use Illuminate\Support\Str;
use InvalidArgumentException;

final class HomeChurchProposedName
{
    public const PREFIX = 'Family House Home Church';

    public static function fromResidenceFamily(string $familyName): string
    {
        $family = Str::squish($familyName);

        if ($family === '') {
            throw new InvalidArgumentException('A family name is required for the home church name.');
        }

        if (Str::length($family) > 100) {
            throw new InvalidArgumentException('Family names must contain at most 100 characters.');
        }

        $composed = self::PREFIX.' @ '.$family.' Residence';

        if (Str::length($composed) > 191) {
            throw new InvalidArgumentException('The composed home church name is too long.');
        }

        return $composed;
    }
}
