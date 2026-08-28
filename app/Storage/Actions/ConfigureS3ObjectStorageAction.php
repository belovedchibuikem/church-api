<?php

namespace App\Storage\Actions;

use App\Exceptions\ObjectStorageLocationInUseException;
use App\Models\FileAsset;
use App\Models\ObjectStorageConfiguration;
use App\Models\User;
use App\Storage\Data\S3ConnectionData;
use App\Storage\ObjectStorageDriver;
use App\Storage\S3EndpointSecurityPolicy;
use App\Storage\StorageProvider;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class ConfigureS3ObjectStorageAction
{
    public function __construct(
        private readonly S3EndpointSecurityPolicy $endpointSecurityPolicy,
        private readonly RecordAuditEventAction $recordAuditEvent,
    ) {}

    /**
     * Store an S3 connection without activating it.
     */
    public function handle(S3ConnectionData $data, ?User $actor = null): ObjectStorageConfiguration
    {
        $this->endpointSecurityPolicy->assertUrlIsSafe($data->endpoint, 'endpoint', allowPath: false);
        $this->endpointSecurityPolicy->assertUrlIsSafe($data->url, 'url', allowPath: true);

        return DB::transaction(function () use ($data, $actor): ObjectStorageConfiguration {
            $configuration = ObjectStorageConfiguration::query()
                ->where('driver', ObjectStorageDriver::S3)
                ->lockForUpdate()
                ->firstOrNew(['driver' => ObjectStorageDriver::S3]);

            $attributes = [
                'access_key_id' => $data->accessKeyId,
                'secret_access_key' => $data->secretAccessKey,
                'region' => $data->region,
                'bucket' => $data->bucket,
                'endpoint' => $data->endpoint,
                'url' => $data->url,
                'root_prefix' => $data->rootPrefix,
                'use_path_style_endpoint' => $data->usePathStyleEndpoint,
            ];

            if ($configuration->exists && $this->configurationMatches($configuration, $attributes)) {
                return $configuration;
            }

            $locationChanged = $configuration->exists
                && ! $this->locationMatches($configuration, $attributes);

            if ($locationChanged && $this->locationHasFileAssets($configuration)) {
                throw new ObjectStorageLocationInUseException(
                    'The object-storage location cannot change while file assets still reference it.',
                );
            }

            $configuration->fill($attributes);
            $configuration->forceFill([
                'is_active' => false,
                'configuration_revision' => $locationChanged
                    ? $configuration->configuration_revision + 1
                    : ($configuration->exists ? $configuration->configuration_revision : 1),
                'last_validation_status' => null,
                'last_validation_failure_code' => null,
                'last_validation_attempted_at' => null,
                'validated_at' => null,
                'activated_at' => null,
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'platform.object_storage.configured',
                actor: $actor,
                targetType: 'object_storage_configuration',
                targetId: 's3',
                scopeType: 'global',
                scopeId: 'platform',
                metadata: [
                    'configuration_revision' => $configuration->configuration_revision,
                    'location_changed' => $locationChanged,
                    'has_custom_endpoint' => $configuration->endpoint !== null,
                    'has_public_url' => $configuration->url !== null,
                    'use_path_style_endpoint' => $configuration->use_path_style_endpoint,
                ],
            ));

            return $configuration;
        }, attempts: 3);
    }

    /**
     * @param  array<string, string|bool|null>  $attributes
     */
    private function configurationMatches(
        ObjectStorageConfiguration $configuration,
        array $attributes,
    ): bool {
        foreach ($attributes as $attribute => $value) {
            if ($configuration->getAttribute($attribute) !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string|bool|null>  $attributes
     */
    private function locationMatches(
        ObjectStorageConfiguration $configuration,
        array $attributes,
    ): bool {
        foreach ([
            'region',
            'bucket',
            'endpoint',
            'url',
            'root_prefix',
            'use_path_style_endpoint',
        ] as $attribute) {
            if ($configuration->getAttribute($attribute) !== $attributes[$attribute]) {
                return false;
            }
        }

        return true;
    }

    private function locationHasFileAssets(ObjectStorageConfiguration $configuration): bool
    {
        return FileAsset::query()
            ->where('storage_provider', StorageProvider::S3->value)
            ->where('disk_name', 'object-storage')
            ->where('storage_configuration_revision', $configuration->configuration_revision)
            ->exists();
    }
}
