<?php

namespace App\Http\Resources\Api\V1\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class CurrentBrowserUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'person_id' => $this->person?->public_id,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'profile' => [
                'given_name' => $this->person?->profile?->given_name,
                'middle_name' => $this->person?->profile?->middle_name,
                'family_name' => $this->person?->profile?->family_name,
                'preferred_name' => $this->person?->profile?->preferred_name,
                'country' => $this->person?->profile?->country,
                'region' => $this->person?->profile?->region,
                'locality' => $this->person?->profile?->locality,
            ],
        ];
    }
}
