<?php

namespace App\Files\Data;

final readonly class InspectedFile
{
    public function __construct(
        public string $detectedMimeType,
        public int $byteSize,
        public string $sha256,
        public ?string $sanitizedOriginalFilename,
    ) {}
}
