<?php

namespace App\Mission\Data;

use App\Models\Person;

final readonly class CaptureMissionSoulData
{
    public function __construct(
        public string $idempotencyKey,
        public ?Person $person = null,
        public ?string $givenName = null,
        public ?string $familyName = null,
        public ?string $middleName = null,
        public ?string $preferredName = null,
    ) {}
}
