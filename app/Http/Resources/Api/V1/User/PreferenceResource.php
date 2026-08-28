<?php

namespace App\Http\Resources\Api\V1\User;

use App\Models\PersonPreference;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PersonPreference */
class PreferenceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'notification_channels' => $this->notification_channels,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
