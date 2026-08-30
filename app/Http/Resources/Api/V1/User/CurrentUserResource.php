<?php

namespace App\Http\Resources\Api\V1\User;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class CurrentUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $profile = $this->person?->profile;
        $avatar = $profile?->relationLoaded('avatarFileAsset')
            ? $profile->avatarFileAsset
            : $profile?->avatarFileAsset()->first();

        return [
            'person_id' => $this->person?->public_id,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'profile' => [
                'given_name' => $profile?->given_name,
                'middle_name' => $profile?->middle_name,
                'family_name' => $profile?->family_name,
                'preferred_name' => $profile?->preferred_name,
                'country' => $profile?->country,
                'region' => $profile?->region,
                'locality' => $profile?->locality,
                'avatar_file_id' => $avatar?->public_id,
            ],
            'preferences' => $this->person?->preference === null
                ? null
                : PreferenceResource::make($this->person->preference),
        ];
    }
}
