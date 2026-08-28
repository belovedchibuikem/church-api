<?php

namespace App\Storage;

use App\Exceptions\ObjectStorageLocationUnavailableException;
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
        return $this->resolve()->disk;
    }

    public function resolve(): ResolvedObjectStorageDisk
    {
        $configuration = ObjectStorageConfiguration::query()
            ->where('driver', ObjectStorageDriver::S3)
            ->where('is_active', true)
            ->whereNotNull('validated_at')
            ->first();

        if ($configuration === null) {
            return new ResolvedObjectStorageDisk(
                provider: StorageProvider::Local,
                diskName: 'local',
                configurationRevision: null,
                disk: $this->filesystems->disk('local'),
            );
        }

        return new ResolvedObjectStorageDisk(
            provider: StorageProvider::S3,
            diskName: 'object-storage',
            configurationRevision: $configuration->configuration_revision,
            disk: $this->filesystems->build(
                $this->configurationFactory->make($configuration),
            ),
        );
    }

    public function diskFor(
        StorageProvider $provider,
        string $diskName,
        ?int $configurationRevision,
    ): Filesystem {
        if ($provider === StorageProvider::Local && $diskName === 'local' && $configurationRevision === null) {
            return $this->filesystems->disk('local');
        }

        if ($provider !== StorageProvider::S3 || $diskName !== 'object-storage' || $configurationRevision === null) {
            throw new ObjectStorageLocationUnavailableException;
        }

        $configuration = ObjectStorageConfiguration::query()
            ->where('driver', ObjectStorageDriver::S3)
            ->where('configuration_revision', $configurationRevision)
            ->first();

        if ($configuration === null) {
            throw new ObjectStorageLocationUnavailableException;
        }

        return $this->filesystems->build(
            $this->configurationFactory->make($configuration),
        );
    }
}
