<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ObjectStorageConfigurationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'provider' => 's3',
            'configured' => true,
            'active' => $this->is_active,
            'credentials_configured' => $this->access_key_id !== null && $this->secret_access_key !== null,
            'region' => $this->region,
            'bucket' => $this->bucket,
            'endpoint' => $this->endpoint,
            'url' => $this->url,
            'root_prefix' => $this->root_prefix,
            'use_path_style_endpoint' => $this->use_path_style_endpoint,
            'configuration_revision' => $this->configuration_revision,
            'validation' => [
                'status' => $this->last_validation_status?->value,
                'failure_code' => $this->last_validation_failure_code,
                'attempted_at' => $this->last_validation_attempted_at?->utc()->toIso8601String(),
                'validated_at' => $this->validated_at?->utc()->toIso8601String(),
            ],
            'activated_at' => $this->activated_at?->utc()->toIso8601String(),
        ];
    }
}
