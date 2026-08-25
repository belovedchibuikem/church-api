<?php

namespace App\Storage\Actions;

use App\Models\ObjectStorageConfiguration;
use App\Storage\Data\S3ConnectionData;
use App\Storage\ObjectStorageDriver;
use Illuminate\Support\Facades\DB;

class ConfigureS3ObjectStorageAction
{
    /**
     * Store an S3 connection without activating it.
     */
    public function handle(S3ConnectionData $data): ObjectStorageConfiguration
    {
        return DB::transaction(function () use ($data): ObjectStorageConfiguration {
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

            $configuration->fill($attributes);
            $configuration->forceFill([
                'is_active' => false,
                'configuration_revision' => $configuration->exists
                    ? $configuration->configuration_revision + 1
                    : 1,
                'last_validation_status' => null,
                'last_validation_failure_code' => null,
                'last_validation_attempted_at' => null,
                'validated_at' => null,
                'activated_at' => null,
            ])->save();

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
}
