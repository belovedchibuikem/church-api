<?php

namespace App\Storage;

use App\Models\ObjectStorageConfiguration;
use App\Storage\Contracts\ObjectStorageConnectionValidator;
use App\Storage\Data\ObjectStorageValidationResult;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;
use Throwable;

class S3ObjectStorageConnectionValidator implements ObjectStorageConnectionValidator
{
    public function __construct(
        private FilesystemManager $filesystems,
        private S3FilesystemConfigurationFactory $configurationFactory,
    ) {}

    public function validate(ObjectStorageConfiguration $configuration): ObjectStorageValidationResult
    {
        if ($configuration->driver !== ObjectStorageDriver::S3) {
            return ObjectStorageValidationResult::failed('unsupported_driver');
        }

        $probePath = '.family-house-connect/connectivity/'.Str::uuid();
        $disk = null;
        $probeWasCreated = false;
        $probeWasRemoved = false;

        try {
            $disk = $this->filesystems->build(
                $this->configurationFactory->make($configuration),
            );

            $probeWasCreated = $disk->put($probePath, 'connectivity-check');

            if (! $probeWasCreated) {
                return ObjectStorageValidationResult::failed('write_failed');
            }

            if (! $disk->exists($probePath)) {
                return ObjectStorageValidationResult::failed('read_after_write_failed');
            }

            $probeWasRemoved = $disk->delete($probePath);

            if (! $probeWasRemoved) {
                return ObjectStorageValidationResult::failed('cleanup_failed');
            }

            return ObjectStorageValidationResult::succeeded();
        } catch (Throwable) {
            return ObjectStorageValidationResult::failed('connection_failed');
        } finally {
            if ($disk !== null && $probeWasCreated && ! $probeWasRemoved) {
                try {
                    $disk->delete($probePath);
                } catch (Throwable) {
                    // The stable failure code is intentionally returned without provider details.
                }
            }
        }
    }
}
