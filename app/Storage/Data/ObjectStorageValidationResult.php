<?php

namespace App\Storage\Data;

use App\Storage\ObjectStorageValidationStatus;

final readonly class ObjectStorageValidationResult
{
    private function __construct(
        public ObjectStorageValidationStatus $status,
        public ?string $failureCode,
    ) {}

    public static function succeeded(): self
    {
        return new self(ObjectStorageValidationStatus::Succeeded, null);
    }

    public static function failed(string $failureCode): self
    {
        return new self(ObjectStorageValidationStatus::Failed, $failureCode);
    }

    public function isSuccessful(): bool
    {
        return $this->status === ObjectStorageValidationStatus::Succeeded;
    }
}
