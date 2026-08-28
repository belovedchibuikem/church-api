<?php

namespace App\Support\Security;

final readonly class RegisterDeviceData
{
    public function __construct(
        public string $identifier,
        public ?string $label = null,
        public ?string $deviceType = null,
        public ?string $platform = null,
        public ?string $appVersion = null,
    ) {}
}
