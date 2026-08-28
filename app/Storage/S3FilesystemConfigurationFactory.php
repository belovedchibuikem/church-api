<?php

namespace App\Storage;

use App\Models\ObjectStorageConfiguration;

class S3FilesystemConfigurationFactory
{
    public function __construct(private readonly S3EndpointSecurityPolicy $endpointSecurityPolicy) {}

    /**
     * @return array<string, mixed>
     */
    public function make(ObjectStorageConfiguration $configuration): array
    {
        $this->endpointSecurityPolicy->assertConfigurationIsSafe($configuration);

        $diskConfiguration = [
            'driver' => ObjectStorageDriver::S3->value,
            'key' => $configuration->access_key_id,
            'secret' => $configuration->secret_access_key,
            'region' => $configuration->region,
            'bucket' => $configuration->bucket,
            'use_path_style_endpoint' => $configuration->use_path_style_endpoint,
            'throw' => true,
            'report' => false,
            'http' => [
                'allow_redirects' => false,
                'connect_timeout' => 5,
                'timeout' => 15,
            ],
        ];

        foreach (['endpoint', 'url', 'root_prefix'] as $optionalAttribute) {
            $value = $configuration->getAttribute($optionalAttribute);

            if ($value !== null && $value !== '') {
                $diskConfiguration[$optionalAttribute === 'root_prefix' ? 'root' : $optionalAttribute] = $value;
            }
        }

        return $diskConfiguration;
    }
}
