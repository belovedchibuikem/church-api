<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeatureFlagResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'key' => $this->key,
            'environment' => $this->environment,
            'scope' => $this->scope_type === null ? null : [
                'type' => $this->scope_type,
                'id' => $this->scope_key,
            ],
            'enabled' => $this->is_enabled,
            'rollout_percentage' => $this->rollout_percentage,
            'starts_at' => $this->starts_at?->utc()->toIso8601String(),
            'ends_at' => $this->ends_at?->utc()->toIso8601String(),
            'updated_at' => $this->updated_at?->utc()->toIso8601String(),
        ];
    }
}
