<?php

namespace Tests\Feature;

use App\Exceptions\ObjectStorageConnectionValidationException;
use App\Models\ObjectStorageConfiguration;
use App\Storage\Actions\ActivateLocalStorageAction;
use App\Storage\Actions\ActivateObjectStorageAction;
use App\Storage\Actions\ConfigureS3ObjectStorageAction;
use App\Storage\Actions\ValidateObjectStorageConnectionAction;
use App\Storage\Contracts\ObjectStorageConnectionValidator;
use App\Storage\Contracts\ObjectStorageDiskResolver;
use App\Storage\Data\ObjectStorageValidationResult;
use App\Storage\Data\S3ConnectionData;
use App\Storage\ObjectStorageValidationStatus;
use App\Storage\S3FilesystemConfigurationFactory;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ObjectStorageConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_credentials_are_encrypted_at_rest_and_hidden_from_serialization(): void
    {
        $configuration = ObjectStorageConfiguration::factory()->create([
            'access_key_id' => 'plain-access-key',
            'secret_access_key' => 'plain-secret-key',
        ]);

        $rawConfiguration = DB::table($configuration->getTable())
            ->where('id', $configuration->getKey())
            ->first();

        $this->assertNotNull($rawConfiguration);
        $this->assertNotSame('plain-access-key', $rawConfiguration->access_key_id);
        $this->assertNotSame('plain-secret-key', $rawConfiguration->secret_access_key);
        $this->assertArrayNotHasKey('access_key_id', $configuration->toArray());
        $this->assertArrayNotHasKey('secret_access_key', $configuration->toArray());
    }

    public function test_local_disk_is_used_until_an_object_storage_connection_is_active(): void
    {
        Storage::fake('local');

        $disk = $this->app->make(ObjectStorageDiskResolver::class)->disk();
        $disk->put('object-storage-default.txt', 'local');

        Storage::disk('local')->assertExists('object-storage-default.txt');
    }

    public function test_installed_s3_adapter_can_build_an_on_demand_disk_without_a_remote_request(): void
    {
        $configuration = ObjectStorageConfiguration::factory()->create();

        $disk = $this->app->make(FilesystemManager::class)->build(
            $this->app->make(S3FilesystemConfigurationFactory::class)->make($configuration),
        );

        $this->assertInstanceOf(AwsS3V3Adapter::class, $disk);
    }

    public function test_changed_connection_is_saved_inactive_and_requires_revalidation(): void
    {
        $configuration = ObjectStorageConfiguration::factory()->active()->create();
        $originalRevision = $configuration->configuration_revision;

        $configured = $this->app->make(ConfigureS3ObjectStorageAction::class)->handle(
            new S3ConnectionData(
                accessKeyId: $configuration->access_key_id,
                secretAccessKey: $configuration->secret_access_key,
                region: $configuration->region,
                bucket: 'replacement-bucket',
                endpoint: $configuration->endpoint,
                url: $configuration->url,
                rootPrefix: $configuration->root_prefix,
                usePathStyleEndpoint: $configuration->use_path_style_endpoint,
            ),
        );

        $this->assertFalse($configured->is_active);
        $this->assertSame($originalRevision + 1, $configured->configuration_revision);
        $this->assertNull($configured->last_validation_status);
        $this->assertNull($configured->validated_at);
        $this->assertNull($configured->activated_at);
    }

    public function test_unchanged_connection_does_not_disable_an_active_configuration(): void
    {
        $configuration = ObjectStorageConfiguration::factory()->active()->create();

        $configured = $this->app->make(ConfigureS3ObjectStorageAction::class)->handle(
            new S3ConnectionData(
                accessKeyId: $configuration->access_key_id,
                secretAccessKey: $configuration->secret_access_key,
                region: $configuration->region,
                bucket: $configuration->bucket,
                endpoint: $configuration->endpoint,
                url: $configuration->url,
                rootPrefix: $configuration->root_prefix,
                usePathStyleEndpoint: $configuration->use_path_style_endpoint,
            ),
        );

        $this->assertTrue($configured->is_active);
        $this->assertSame($configuration->configuration_revision, $configured->configuration_revision);
        $this->assertSame(ObjectStorageValidationStatus::Succeeded, $configured->last_validation_status);
    }

    public function test_failed_validation_keeps_local_storage_active_and_records_only_a_stable_code(): void
    {
        $configuration = ObjectStorageConfiguration::factory()->create();
        $this->app->instance(
            ObjectStorageConnectionValidator::class,
            new FixedObjectStorageConnectionValidator(
                ObjectStorageValidationResult::failed('connection_failed'),
            ),
        );

        try {
            $this->app->make(ActivateObjectStorageAction::class)->handle($configuration);
            $this->fail('Expected object storage activation to fail.');
        } catch (ObjectStorageConnectionValidationException $exception) {
            $this->assertSame('connection_failed', $exception->failureCode);
        }

        $configuration->refresh();
        $this->assertFalse($configuration->is_active);
        $this->assertSame(ObjectStorageValidationStatus::Failed, $configuration->last_validation_status);
        $this->assertSame('connection_failed', $configuration->last_validation_failure_code);
        $this->assertNull($configuration->validated_at);
        $this->assertNull($configuration->activated_at);
    }

    public function test_successful_validation_activates_the_connection(): void
    {
        $configuration = ObjectStorageConfiguration::factory()->create();
        $this->app->instance(
            ObjectStorageConnectionValidator::class,
            new FixedObjectStorageConnectionValidator(ObjectStorageValidationResult::succeeded()),
        );

        $activated = $this->app->make(ActivateObjectStorageAction::class)->handle($configuration);

        $this->assertTrue($activated->is_active);
        $this->assertSame(ObjectStorageValidationStatus::Succeeded, $activated->last_validation_status);
        $this->assertNotNull($activated->last_validation_attempted_at);
        $this->assertNotNull($activated->validated_at);
        $this->assertNotNull($activated->activated_at);
    }

    public function test_failed_revalidation_deactivates_an_existing_connection(): void
    {
        $configuration = ObjectStorageConfiguration::factory()->active()->create();
        $this->app->instance(
            ObjectStorageConnectionValidator::class,
            new FixedObjectStorageConnectionValidator(
                ObjectStorageValidationResult::failed('connection_failed'),
            ),
        );

        $result = $this->app->make(ValidateObjectStorageConnectionAction::class)
            ->handle($configuration);

        $configuration->refresh();
        $this->assertFalse($result->isSuccessful());
        $this->assertFalse($configuration->is_active);
        $this->assertNull($configuration->validated_at);
        $this->assertNull($configuration->activated_at);
    }

    public function test_local_storage_can_be_reactivated_without_deleting_s3_configuration(): void
    {
        Storage::fake('local');
        $configuration = ObjectStorageConfiguration::factory()->active()->create();

        $this->app->make(ActivateLocalStorageAction::class)->handle();

        $configuration->refresh();
        $this->assertFalse($configuration->is_active);
        $this->assertNull($configuration->activated_at);
        $this->assertNotNull($configuration->validated_at);

        $disk = $this->app->make(ObjectStorageDiskResolver::class)->disk();
        $disk->put('local-after-s3.txt', 'local');
        Storage::disk('local')->assertExists('local-after-s3.txt');
    }
}

final readonly class FixedObjectStorageConnectionValidator implements ObjectStorageConnectionValidator
{
    public function __construct(
        private ObjectStorageValidationResult $result,
    ) {}

    public function validate(ObjectStorageConfiguration $configuration): ObjectStorageValidationResult
    {
        return $this->result;
    }
}
