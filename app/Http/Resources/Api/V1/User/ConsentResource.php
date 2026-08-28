<?php

namespace App\Http\Resources\Api\V1\User;

use App\Models\PersonConsent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PersonConsent */
class ConsentResource extends JsonResource
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
            'purpose' => $this->purpose,
            'policy_version' => $this->policy_version,
            'source' => $this->source,
            'granted_at' => $this->granted_at?->toIso8601String(),
            'withdrawn_at' => $this->withdrawn_at?->toIso8601String(),
        ];
    }
}
