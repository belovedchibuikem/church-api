<?php

namespace App\Support\Organization;

use InvalidArgumentException;

final readonly class GeographicCoordinates
{
    public function __construct(
        public ?float $latitude,
        public ?float $longitude,
    ) {
        if (($this->latitude === null) !== ($this->longitude === null)) {
            throw new InvalidArgumentException('Latitude and longitude must be provided together.');
        }

        if ($this->latitude === null) {
            return;
        }

        if (
            ! is_finite($this->latitude)
            || ! is_finite($this->longitude)
            || $this->latitude < -90
            || $this->latitude > 90
            || $this->longitude < -180
            || $this->longitude > 180
        ) {
            throw new InvalidArgumentException('Coordinates must be valid latitude and longitude values.');
        }
    }
}
