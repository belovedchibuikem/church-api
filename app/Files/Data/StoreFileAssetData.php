<?php

namespace App\Files\Data;

use App\Files\FileAssetClassification;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\UploadedFile;

final readonly class StoreFileAssetData
{
    public function __construct(
        public UploadedFile $file,
        public string $purpose,
        public FileAssetClassification $classification,
        public string $idempotencyKey,
        public ?Person $owner = null,
        public ?User $actor = null,
    ) {}
}
