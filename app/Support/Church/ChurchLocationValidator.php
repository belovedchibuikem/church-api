<?php

namespace App\Support\Church;

use App\Models\AdministrativeUnit;
use App\Models\Location;
use InvalidArgumentException;

class ChurchLocationValidator
{
    public function assertAligned(Location $location, AdministrativeUnit $administrativeUnit): void
    {
        if (
            $location->administrative_unit_id !== $administrativeUnit->getKey()
            || $location->country_id !== $administrativeUnit->country_id
        ) {
            throw new InvalidArgumentException(
                'Church locations must belong to the selected administrative unit and country.',
            );
        }
    }
}
