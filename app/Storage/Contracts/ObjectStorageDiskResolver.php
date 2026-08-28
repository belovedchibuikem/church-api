<?php

namespace App\Storage\Contracts;

use App\Storage\ResolvedObjectStorageDisk;
use App\Storage\StorageProvider;
use Illuminate\Contracts\Filesystem\Filesystem;

interface ObjectStorageDiskResolver
{
    public function disk(): Filesystem;

    public function resolve(): ResolvedObjectStorageDisk;

    public function diskFor(
        StorageProvider $provider,
        string $diskName,
        ?int $configurationRevision,
    ): Filesystem;
}
