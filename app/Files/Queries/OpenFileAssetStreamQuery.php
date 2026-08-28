<?php

namespace App\Files\Queries;

use App\Exceptions\FileAssetUnavailableException;
use App\Files\FileAssetStatus;
use App\Models\FileAsset;
use App\Storage\Contracts\ObjectStorageDiskResolver;

class OpenFileAssetStreamQuery
{
    public function __construct(
        private ObjectStorageDiskResolver $storageResolver,
    ) {}

    /**
     * @return resource
     */
    public function handle(FileAsset $fileAsset)
    {
        if ($fileAsset->status !== FileAssetStatus::Available || $fileAsset->deleted_at !== null) {
            throw new FileAssetUnavailableException;
        }

        $disk = $this->storageResolver->diskFor(
            $fileAsset->storage_provider,
            $fileAsset->disk_name,
            $fileAsset->storage_configuration_revision,
        );
        $stream = $disk->readStream($fileAsset->object_key);

        if ($stream === false) {
            throw new FileAssetUnavailableException;
        }

        return $stream;
    }
}
