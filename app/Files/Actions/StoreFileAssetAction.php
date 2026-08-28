<?php

namespace App\Files\Actions;

use App\Exceptions\FileAssetIdempotencyConflictException;
use App\Exceptions\FileAssetValidationException;
use App\Files\Contracts\FileContentPolicy;
use App\Files\Contracts\MalwareScanner;
use App\Files\Data\InspectedFile;
use App\Files\Data\StoreFileAssetData;
use App\Files\FileAssetStatus;
use App\Files\MalwareScanStatus;
use App\Models\FileAsset;
use App\Storage\Contracts\ObjectStorageDiskResolver;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class StoreFileAssetAction
{
    public function __construct(
        private FileContentPolicy $contentPolicy,
        private MalwareScanner $malwareScanner,
        private ObjectStorageDiskResolver $storageResolver,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(StoreFileAssetData $data): FileAsset
    {
        $this->validateData($data);

        $inspectedFile = $this->contentPolicy->inspect($data->file);
        $idempotencyKeyHash = hash_hmac('sha256', $data->idempotencyKey, $this->hashKey());
        $ownerKey = $data->owner?->getKey() === null ? 'system' : (string) $data->owner->getKey();
        $idempotencyScopeHash = hash_hmac(
            'sha256',
            "{$ownerKey}|{$data->purpose}|{$idempotencyKeyHash}",
            $this->hashKey(),
        );

        return Cache::lock("file-assets:idempotency:{$idempotencyScopeHash}", 60)
            ->block(10, function () use (
                $data,
                $inspectedFile,
                $idempotencyKeyHash,
                $idempotencyScopeHash,
            ): FileAsset {
                $existingAsset = FileAsset::query()
                    ->where('idempotency_scope_hash', $idempotencyScopeHash)
                    ->first();

                if ($existingAsset !== null) {
                    return $this->matchExistingAsset($existingAsset, $data, $inspectedFile);
                }

                return $this->storeNewAsset(
                    $data,
                    $inspectedFile,
                    $idempotencyKeyHash,
                    $idempotencyScopeHash,
                );
            });
    }

    private function storeNewAsset(
        StoreFileAssetData $data,
        InspectedFile $inspectedFile,
        string $idempotencyKeyHash,
        string $idempotencyScopeHash,
    ): FileAsset {
        $path = $data->file->getRealPath();

        if (! is_string($path) || $path === '') {
            throw new FileAssetValidationException('upload_unreadable');
        }

        $scanStatus = $this->malwareScanner->scan(
            $path,
            $inspectedFile->detectedMimeType,
            $inspectedFile->sha256,
        );
        $resolvedStorage = $this->storageResolver->resolve();
        $objectKey = $this->generateObjectKey();
        $assetStatus = $this->assetStatus($scanStatus);
        $objectWasWritten = false;

        if ($scanStatus !== MalwareScanStatus::Infected) {
            $this->writePrivateObject(
                $resolvedStorage->disk,
                $objectKey,
                $path,
                $inspectedFile->detectedMimeType,
            );
            $objectWasWritten = true;
        }

        try {
            return DB::transaction(function () use (
                $data,
                $inspectedFile,
                $idempotencyKeyHash,
                $idempotencyScopeHash,
                $scanStatus,
                $resolvedStorage,
                $objectKey,
                $assetStatus,
            ): FileAsset {
                $now = now()->utc();
                $fileAsset = (new FileAsset)->forceFill([
                    'owner_person_id' => $data->owner?->getKey(),
                    'purpose' => $data->purpose,
                    'classification' => $data->classification,
                    'storage_provider' => $resolvedStorage->provider,
                    'disk_name' => $resolvedStorage->diskName,
                    'storage_configuration_revision' => $resolvedStorage->configurationRevision,
                    'object_key' => $objectKey,
                    'metadata' => $inspectedFile->sanitizedOriginalFilename === null
                        ? []
                        : ['original_filename' => $inspectedFile->sanitizedOriginalFilename],
                    'detected_mime_type' => $inspectedFile->detectedMimeType,
                    'byte_size' => $inspectedFile->byteSize,
                    'sha256' => $inspectedFile->sha256,
                    'idempotency_key_hash' => $idempotencyKeyHash,
                    'idempotency_scope_hash' => $idempotencyScopeHash,
                    'status' => $assetStatus,
                    'malware_scan_status' => $scanStatus,
                    'malware_scanned_at' => $scanStatus === MalwareScanStatus::Pending ? null : $now,
                    'rejection_reason' => $scanStatus === MalwareScanStatus::Infected
                        ? 'malware_detected'
                        : null,
                    'rejected_at' => $scanStatus === MalwareScanStatus::Infected ? $now : null,
                ]);
                $fileAsset->save();

                $this->recordAuditEvent->handle(new AuditEventData(
                    action: 'files.asset.stored',
                    actor: $data->actor,
                    targetType: 'file_asset',
                    targetId: $fileAsset->public_id,
                    metadata: [
                        'purpose' => $data->purpose,
                        'classification' => $data->classification->value,
                        'status' => $assetStatus->value,
                        'detected_mime_type' => $inspectedFile->detectedMimeType,
                        'byte_size' => $inspectedFile->byteSize,
                        'storage_provider' => $resolvedStorage->provider->value,
                    ],
                ));

                return $fileAsset;
            }, attempts: 3);
        } catch (Throwable $exception) {
            if ($objectWasWritten) {
                $resolvedStorage->disk->delete($objectKey);
            }

            throw $exception;
        }
    }

    private function matchExistingAsset(
        FileAsset $existingAsset,
        StoreFileAssetData $data,
        InspectedFile $inspectedFile,
    ): FileAsset {
        if (
            $existingAsset->owner_person_id !== $data->owner?->getKey()
            || $existingAsset->purpose !== $data->purpose
            || $existingAsset->classification !== $data->classification
            || $existingAsset->detected_mime_type !== $inspectedFile->detectedMimeType
            || $existingAsset->byte_size !== $inspectedFile->byteSize
            || ! hash_equals($existingAsset->sha256, $inspectedFile->sha256)
        ) {
            throw new FileAssetIdempotencyConflictException;
        }

        return $existingAsset;
    }

    private function writePrivateObject(
        Filesystem $disk,
        string $objectKey,
        string $path,
        string $detectedMimeType,
    ): void {
        $stream = fopen($path, 'rb');

        if ($stream === false) {
            throw new FileAssetValidationException('upload_unreadable');
        }

        try {
            $stored = $disk->put($objectKey, $stream, [
                'visibility' => 'private',
                'ContentType' => $detectedMimeType,
            ]);
        } finally {
            fclose($stream);
        }

        if (! $stored) {
            throw new FileAssetValidationException('storage_write_failed');
        }
    }

    private function assetStatus(MalwareScanStatus $scanStatus): FileAssetStatus
    {
        return match ($scanStatus) {
            MalwareScanStatus::Clean => FileAssetStatus::Pending,
            MalwareScanStatus::Infected => FileAssetStatus::Rejected,
            MalwareScanStatus::Pending, MalwareScanStatus::Failed => FileAssetStatus::Quarantined,
        };
    }

    private function generateObjectKey(): string
    {
        return 'assets/quarantine/'.now()->utc()->format('Y/m').'/'.Str::ulid().'-'.Str::random(32);
    }

    private function validateData(StoreFileAssetData $data): void
    {
        if (
            Str::length($data->purpose) > 100
            || ! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $data->purpose)
        ) {
            throw new InvalidArgumentException('The file purpose must be a stable lowercase identifier.');
        }

        if ($data->idempotencyKey === '' || Str::length($data->idempotencyKey) > 255) {
            throw new InvalidArgumentException('The idempotency key must contain between 1 and 255 characters.');
        }

        if ($data->owner !== null && ! $data->owner->exists) {
            throw new InvalidArgumentException('The file owner must be persisted before storing an asset.');
        }
    }

    private function hashKey(): string
    {
        $key = config('app.key');

        if (! is_string($key) || $key === '') {
            throw new InvalidArgumentException('The application key is required for file idempotency protection.');
        }

        return $key;
    }
}
