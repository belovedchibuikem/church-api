<?php

namespace App\Http\Resources\Api\V1\Auth;

use App\Support\Security\IssuedMobileCredentials;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin IssuedMobileCredentials */
class MobileCredentialResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token_type' => 'Bearer',
            'access_token' => $this->plainAccessToken,
            'access_token_expires_at' => $this->accessToken->expires_at->toIso8601String(),
            'refresh_token' => $this->plainRefreshToken,
            'refresh_token_expires_at' => $this->refreshToken->expires_at->toIso8601String(),
            'security_session_id' => $this->securitySession->public_id,
            'device_id' => $this->device->public_id,
            'mfa_verified_at' => $this->securitySession->mfa_verified_at?->toIso8601String(),
            'user' => [
                'email' => $this->user->email,
                'person_id' => $this->user->person?->public_id,
            ],
        ];
    }
}
