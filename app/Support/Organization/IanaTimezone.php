<?php

namespace App\Support\Organization;

use DateTimeZone;
use InvalidArgumentException;

final readonly class IanaTimezone
{
    public function __construct(public string $value)
    {
        if (! in_array($this->value, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException('Timezone must be a current IANA timezone identifier.');
        }
    }
}
