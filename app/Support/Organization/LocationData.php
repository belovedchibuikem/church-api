<?php

namespace App\Support\Organization;

use App\Models\AdministrativeUnit;
use App\Models\Country;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class LocationData
{
    public string $name;

    public string $timezone;

    public ?string $addressLineOne;

    public ?string $addressLineTwo;

    public ?string $locality;

    public ?string $postalCode;

    public GeographicCoordinates $coordinates;

    public function __construct(
        public Country $country,
        string $name,
        string $timezone,
        public ?AdministrativeUnit $administrativeUnit = null,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $addressLineOne = null,
        ?string $addressLineTwo = null,
        ?string $locality = null,
        ?string $postalCode = null,
    ) {
        $this->name = self::requiredText($name, 191, 'Location name');
        $this->timezone = (new IanaTimezone($timezone))->value;
        $this->coordinates = new GeographicCoordinates($latitude, $longitude);
        $this->addressLineOne = self::optionalText($addressLineOne, 191, 'Address line one');
        $this->addressLineTwo = self::optionalText($addressLineTwo, 191, 'Address line two');
        $this->locality = self::optionalText($locality, 191, 'Locality');
        $this->postalCode = self::optionalText($postalCode, 32, 'Postal code');
    }

    private static function requiredText(string $value, int $maximum, string $name): string
    {
        $normalized = Str::squish($value);

        if ($normalized === '' || Str::length($normalized) > $maximum) {
            throw new InvalidArgumentException("{$name} must contain between 1 and {$maximum} characters.");
        }

        return $normalized;
    }

    private static function optionalText(?string $value, int $maximum, string $name): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = Str::squish($value);

        if ($normalized === '') {
            return null;
        }

        if (Str::length($normalized) > $maximum) {
            throw new InvalidArgumentException("{$name} cannot exceed {$maximum} characters.");
        }

        return $normalized;
    }
}
