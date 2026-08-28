<?php

namespace Tests\Feature;

use App\Exceptions\FileAssetIdempotencyConflictException;
use App\Exceptions\FileAssetUnavailableException;
use App\Exceptions\FileAssetValidationException;
use App\Files\Actions\StoreFileAssetAction;
use App\Files\Contracts\MalwareScanner;
use App\Files\Data\StoreFileAssetData;
use App\Files\FileAssetClassification;
use App\Files\FileAssetStatus;
use App\Files\MalwareScanStatus;
use App\Files\Queries\OpenFileAssetStreamQuery;
use App\Models\AuditEvent;
use App\Models\FileAsset;
use App\Models\Person;
use App\Models\User;
use App\Storage\Contracts\ObjectStorageDiskResolver;
use App\Storage\ResolvedObjectStorageDisk;
use App\Storage\StorageProvider;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class FileAssetPersistenceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_stores_a_private_quarantined_asset_with_sanitized_metadata_and_audit(): void
    {
        Storage::fake('local');
        $owner = Person::factory()->create();
        $actor = User::factory()->create();
        $contents = 'confidential pastoral document';
        $file = UploadedFile::fake()->createWithContent('../../evil<script>.txt', $contents);

        $asset = $this->app->make(StoreFileAssetAction::class)->handle(new StoreFileAssetData(
            file: $file,
            purpose: 'pastoral.document',
            classification: FileAssetClassification::Restricted,
            idempotencyKey: 'upload-request-001',
            owner: $owner,
            actor: $actor,
        ));

        $this->assertModelExists($asset);
        $this->assertTrue(Str::isUlid($asset->public_id));
        $this->assertSame(FileAssetStatus::Quarantined, $asset->status);
        $this->assertSame(MalwareScanStatus::Pending, $asset->malware_scan_status);
        $this->assertSame(StorageProvider::Local, $asset->storage_provider);
        $this->assertSame('local', $asset->disk_name);
        $this->assertNull($asset->storage_configuration_revision);
        $this->assertSame('text/plain', $asset->detected_mime_type);
        $this->assertSame(mb_strlen($contents, '8bit'), $asset->byte_size);
        $this->assertSame(hash('sha256', $contents), $asset->sha256);
        $this->assertStringStartsWith('assets/quarantine/', $asset->object_key);
        $this->assertStringNotContainsString('evil', $asset->object_key);
        $this->assertNotSame('upload-request-001', $asset->idempotency_key_hash);
        $this->assertArrayNotHasKey('object_key', $asset->toArray());
        $this->assertArrayNotHasKey('idempotency_key_hash', $asset->toArray());

        $originalFilename = $asset->metadata['original_filename'];
        $this->assertStringNotContainsString('/', $originalFilename);
        $this->assertStringNotContainsString('\\', $originalFilename);
        $this->assertStringNotContainsString('<', $originalFilename);
        $this->assertStringNotContainsString('>', $originalFilename);

        Storage::disk('local')->assertExists($asset->object_key);
        $this->assertSame(storage_path('app/private'), config('filesystems.disks.local.root'));

        $auditEvent = AuditEvent::query()->sole();
        $this->assertSame('files.asset.stored', $auditEvent->action);
        $this->assertSame($asset->public_id, $auditEvent->target_id);
        $this->assertSame($actor->getKey(), $auditEvent->actor_user_id);
        $this->assertArrayNotHasKey('original_filename', $auditEvent->metadata);
        $this->assertArrayNotHasKey('object_key', $auditEvent->metadata);
    }

    public function test_rejects_an_oversized_file_before_storage(): void
    {
        Storage::fake('local');
        config(['file_assets.maximum_bytes' => 5]);
        $wasRejected = false;

        try {
            $this->storeTextFile('large.txt', '123456', 'oversized-001');
            $this->fail('Expected the oversized file to be rejected.');
        } catch (FileAssetValidationException $exception) {
            $wasRejected = true;
            $this->assertSame('file_too_large', $exception->reasonCode);
        }

        $this->assertTrue($wasRejected);
        $this->assertSame(0, FileAsset::query()->count());
        $this->assertSame(0, AuditEvent::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_requests_private_visibility_when_writing_an_object(): void
    {
        $disk = $this->mock(Filesystem::class);
        $disk->shouldReceive('put')
            ->once()
            ->withArgs(fn (string $objectKey, mixed $stream, array $options): bool => is_resource($stream)
                && Str::startsWith($objectKey, 'assets/quarantine/')
                && $options['visibility'] === 'private'
                && $options['ContentType'] === 'text/plain')
            ->andReturn(true);
        $resolver = new MutableObjectStorageDiskResolver($disk, $disk);
        $resolver->useLocalForWrites();
        $this->app->instance(ObjectStorageDiskResolver::class, $resolver);

        $asset = $this->storeTextFile('private.txt', 'private contents', 'private-001');

        $this->assertModelExists($asset);
        $this->assertSame(StorageProvider::Local, $asset->storage_provider);
        $this->assertSame(FileAssetStatus::Quarantined, $asset->status);
    }

    public function test_uses_detected_content_mime_instead_of_the_client_filename(): void
    {
        Storage::fake('local');
        config(['file_assets.allowed_mime_types' => ['application/pdf']]);
        $wasRejected = false;

        try {
            $this->app->make(StoreFileAssetAction::class)->handle(new StoreFileAssetData(
                file: UploadedFile::fake()->createWithContent('looks-safe.pdf', 'plain text content'),
                purpose: 'document.general',
                classification: FileAssetClassification::Internal,
                idempotencyKey: 'mime-001',
            ));
            $this->fail('Expected content-detected MIME validation to reject the file.');
        } catch (FileAssetValidationException $exception) {
            $wasRejected = true;
            $this->assertSame('mime_type_not_allowed', $exception->reasonCode);
        }

        $this->assertTrue($wasRejected);
        $this->assertSame(0, FileAsset::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_infected_content_is_rejected_without_writing_an_object(): void
    {
        Storage::fake('local');
        $this->app->instance(MalwareScanner::class, new FixedMalwareScanner(MalwareScanStatus::Infected));

        $asset = $this->storeTextFile('infected.txt', 'malicious test payload', 'infected-001');

        $this->assertSame(FileAssetStatus::Rejected, $asset->status);
        $this->assertSame(MalwareScanStatus::Infected, $asset->malware_scan_status);
        $this->assertSame('malware_detected', $asset->rejection_reason);
        $this->assertNotNull($asset->rejected_at);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame('files.asset.stored', AuditEvent::query()->sole()->action);
    }

    public function test_idempotent_retry_returns_the_same_asset_without_a_second_object_or_audit(): void
    {
        Storage::fake('local');

        $firstAsset = $this->storeTextFile('first.txt', 'same contents', 'retry-001');
        $retriedAsset = $this->storeTextFile('renamed.txt', 'same contents', 'retry-001');

        $this->assertSame($firstAsset->getKey(), $retriedAsset->getKey());
        $this->assertSame(1, FileAsset::query()->count());
        $this->assertSame(1, AuditEvent::query()->count());
        $this->assertCount(1, Storage::disk('local')->allFiles('assets'));
    }

    public function test_idempotency_key_reuse_with_different_content_is_rejected(): void
    {
        Storage::fake('local');
        $this->storeTextFile('first.txt', 'first contents', 'conflict-001');
        $wasRejected = false;

        try {
            $this->storeTextFile('second.txt', 'different contents', 'conflict-001');
            $this->fail('Expected conflicting idempotent content to be rejected.');
        } catch (FileAssetIdempotencyConflictException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame(1, FileAsset::query()->count());
        $this->assertSame(1, AuditEvent::query()->count());
        $this->assertCount(1, Storage::disk('local')->allFiles('assets'));
    }

    public function test_idempotency_key_is_scoped_to_owner_and_purpose(): void
    {
        Storage::fake('local');
        $firstOwner = Person::factory()->create();
        $secondOwner = Person::factory()->create();
        $action = $this->app->make(StoreFileAssetAction::class);

        $firstAsset = $action->handle(new StoreFileAssetData(
            file: UploadedFile::fake()->createWithContent('first.txt', 'same contents'),
            purpose: 'document.general',
            classification: FileAssetClassification::Internal,
            idempotencyKey: 'scoped-001',
            owner: $firstOwner,
        ));
        $secondOwnerAsset = $action->handle(new StoreFileAssetData(
            file: UploadedFile::fake()->createWithContent('second.txt', 'same contents'),
            purpose: 'document.general',
            classification: FileAssetClassification::Internal,
            idempotencyKey: 'scoped-001',
            owner: $secondOwner,
        ));
        $secondPurposeAsset = $action->handle(new StoreFileAssetData(
            file: UploadedFile::fake()->createWithContent('third.txt', 'same contents'),
            purpose: 'document.evidence',
            classification: FileAssetClassification::Internal,
            idempotencyKey: 'scoped-001',
            owner: $firstOwner,
        ));

        $this->assertNotSame($firstAsset->getKey(), $secondOwnerAsset->getKey());
        $this->assertNotSame($firstAsset->getKey(), $secondPurposeAsset->getKey());
        $this->assertSame(3, FileAsset::query()->count());
        $this->assertSame(3, AuditEvent::query()->count());
        $this->assertCount(3, Storage::disk('local')->allFiles('assets'));
    }

    public function test_removes_the_private_object_when_transactional_audit_fails(): void
    {
        Storage::fake('local');
        $this->mock(RecordAuditEventAction::class)
            ->shouldReceive('handle')
            ->once()
            ->andThrow(new RuntimeException('Audit database unavailable.'));
        $wasRolledBack = false;

        try {
            $this->storeTextFile('rollback.txt', 'rollback contents', 'rollback-001');
            $this->fail('Expected audit failure to roll back the file asset.');
        } catch (RuntimeException) {
            $wasRolledBack = true;
        }

        $this->assertTrue($wasRolledBack);
        $this->assertSame(0, FileAsset::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_available_reads_use_the_recorded_provider_after_the_write_provider_changes(): void
    {
        Storage::fake('asset-local');
        Storage::fake('asset-s3');
        $resolver = new MutableObjectStorageDiskResolver(
            Storage::disk('asset-local'),
            Storage::disk('asset-s3'),
        );
        $this->app->instance(ObjectStorageDiskResolver::class, $resolver);
        $asset = $this->storeTextFile('provider.txt', 'stored on s3', 'provider-001');
        $asset->forceFill([
            'status' => FileAssetStatus::Available,
            'malware_scan_status' => MalwareScanStatus::Clean,
            'malware_scanned_at' => now(),
            'available_at' => now(),
        ])->save();
        $resolver->useLocalForWrites();

        $stream = $this->app->make(OpenFileAssetStreamQuery::class)->handle($asset);
        $contents = stream_get_contents($stream);
        fclose($stream);

        $this->assertSame('stored on s3', $contents);
        $this->assertSame(StorageProvider::S3, $resolver->lastReadProvider);
        $this->assertSame('object-storage', $resolver->lastReadDiskName);
        $this->assertSame(7, $resolver->lastReadConfigurationRevision);
        Storage::disk('asset-s3')->assertExists($asset->object_key);
        $this->assertSame([], Storage::disk('asset-local')->allFiles());
    }

    public function test_quarantined_asset_cannot_be_read(): void
    {
        Storage::fake('local');
        $asset = $this->storeTextFile('pending.txt', 'not yet available', 'pending-001');
        $wasRejected = false;

        try {
            $this->app->make(OpenFileAssetStreamQuery::class)->handle($asset);
            $this->fail('Expected the quarantined asset read to be rejected.');
        } catch (FileAssetUnavailableException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        Storage::disk('local')->assertExists($asset->object_key);
    }

    private function storeTextFile(
        string $filename,
        string $contents,
        string $idempotencyKey,
    ): FileAsset {
        return $this->app->make(StoreFileAssetAction::class)->handle(new StoreFileAssetData(
            file: UploadedFile::fake()->createWithContent($filename, $contents),
            purpose: 'document.general',
            classification: FileAssetClassification::Internal,
            idempotencyKey: $idempotencyKey,
        ));
    }
}

final readonly class FixedMalwareScanner implements MalwareScanner
{
    public function __construct(private MalwareScanStatus $result) {}

    public function scan(string $path, string $detectedMimeType, string $sha256): MalwareScanStatus
    {
        return $this->result;
    }
}

final class MutableObjectStorageDiskResolver implements ObjectStorageDiskResolver
{
    public ?StorageProvider $lastReadProvider = null;

    public ?string $lastReadDiskName = null;

    public ?int $lastReadConfigurationRevision = null;

    private bool $writeToS3 = true;

    public function __construct(
        private Filesystem $localDisk,
        private Filesystem $s3Disk,
    ) {}

    public function disk(): Filesystem
    {
        return $this->resolve()->disk;
    }

    public function resolve(): ResolvedObjectStorageDisk
    {
        if ($this->writeToS3) {
            return new ResolvedObjectStorageDisk(
                provider: StorageProvider::S3,
                diskName: 'object-storage',
                configurationRevision: 7,
                disk: $this->s3Disk,
            );
        }

        return new ResolvedObjectStorageDisk(
            provider: StorageProvider::Local,
            diskName: 'local',
            configurationRevision: null,
            disk: $this->localDisk,
        );
    }

    public function diskFor(
        StorageProvider $provider,
        string $diskName,
        ?int $configurationRevision,
    ): Filesystem {
        $this->lastReadProvider = $provider;
        $this->lastReadDiskName = $diskName;
        $this->lastReadConfigurationRevision = $configurationRevision;

        return $provider === StorageProvider::S3 ? $this->s3Disk : $this->localDisk;
    }

    public function useLocalForWrites(): void
    {
        $this->writeToS3 = false;
    }
}
