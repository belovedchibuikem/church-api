<?php

namespace App\Storage;

use App\Models\ObjectStorageConfiguration;
use App\Storage\Contracts\ObjectStorageDiskResolver;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;

class DatabaseObjectStorageDiskResolver implements ObjectStorageDiskResolver
{
    public function __construct(
        private FilesystemManager $filesystems,
        private S3FilesystemConfigurationFactory $configurationFactory,
    ) {}

    public function disk(): Filesystem
    {
        $configuration = ObjectStorageConfiguration::query()
            ->where('driver', ObjectStorageDriver::S3)
            ->where('is_active', true)
            ->whereNotNull('validated_at')
            ->first();

        if ($configuration === null) {
            return $this->filesystems->disk('local');
        }

        return $this->filesystems->build(
            $this->configurationFactory->make($configuration),
        );
    }
}
