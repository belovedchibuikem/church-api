<?php

namespace App\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;

final readonly class ResolvedObjectStorageDisk
{
    public function __construct(
        public StorageProvider $provider,
        public string $diskName,
        public ?int $configurationRevision,
        public Filesystem $disk,
    ) {}
}
