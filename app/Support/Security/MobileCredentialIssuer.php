<?php

namespace App\Support\Security;

use App\Models\Device;
use App\Models\MobileAccessToken;
use App\Models\MobileRefreshToken;
use App\Models\SecuritySession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class MobileCredentialIssuer
{
    public function __construct(private MobileCredentialHasher $hasher) {}

    public function issue(
        User $user,
        Device $device,
        SecuritySession $securitySession,
        ?string $familyId = null,
    ): IssuedMobileCredentials {
        $familyId ??= (string) Str::ulid();
        $plainAccessToken = $this->randomCredential();
        $plainRefreshToken = $this->randomCredential();
        $now = now()->utc();
        $accessTtl = min(max((int) config('api.mobile.access_ttl_seconds'), 60), 900);
        $refreshTtl = min(max((int) config('api.mobile.refresh_ttl_seconds'), 3600), 2_592_000);
        $accessExpiresAt = $now->copy()->addSeconds($accessTtl);
        $refreshExpiresAt = $now->copy()->addSeconds($refreshTtl);

        if ($securitySession->expires_at !== null && $refreshExpiresAt->isAfter($securitySession->expires_at)) {
            $refreshExpiresAt = Carbon::instance($securitySession->expires_at);
        }

        $accessToken = MobileAccessToken::query()->create([
            'user_id' => $user->getKey(),
            'security_session_id' => $securitySession->getKey(),
            'device_id' => $device->getKey(),
            'family_id' => $familyId,
            'token_hash' => $this->hasher->hash($plainAccessToken),
            'expires_at' => $accessExpiresAt,
        ]);

        $refreshToken = MobileRefreshToken::query()->create([
            'user_id' => $user->getKey(),
            'security_session_id' => $securitySession->getKey(),
            'device_id' => $device->getKey(),
            'family_id' => $familyId,
            'token_hash' => $this->hasher->hash($plainRefreshToken),
            'expires_at' => $refreshExpiresAt,
        ]);

        $user->loadMissing('person');

        return new IssuedMobileCredentials(
            user: $user,
            device: $device,
            securitySession: $securitySession,
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            plainAccessToken: $plainAccessToken,
            plainRefreshToken: $plainRefreshToken,
        );
    }

    private function randomCredential(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    }
}
