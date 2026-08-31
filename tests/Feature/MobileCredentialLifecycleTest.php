<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\MobileAccessToken;
use App\Models\MobileRefreshToken;
use App\Models\User;
use App\Support\Security\AuthenticateMobileLoginAction;
use App\Support\Security\MobileCredentialHasher;
use App\Support\Security\RefreshMobileCredentialsAction;
use App\Support\Security\RegisterDeviceData;
use App\Support\Security\RevokeDeviceAction;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MobileCredentialLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_login_issues_only_hashed_device_bound_short_lived_credentials(): void
    {
        $this->freezeSecond();
        $user = User::factory()->create(['password' => 'correct-password']);

        $credentials = $this->app->make(AuthenticateMobileLoginAction::class)->handle(
            $user->email,
            'correct-password',
            new RegisterDeviceData(identifier: 'installation-secret', platform: 'Android'),
        );

        $hasher = $this->app->make(MobileCredentialHasher::class);
        $this->assertSame($hasher->hash($credentials->plainAccessToken), $credentials->accessToken->token_hash);
        $this->assertSame($hasher->hash($credentials->plainRefreshToken), $credentials->refreshToken->token_hash);
        $this->assertNotSame($credentials->plainAccessToken, $credentials->accessToken->getRawOriginal('token_hash'));
        $this->assertNotSame($credentials->plainRefreshToken, $credentials->refreshToken->getRawOriginal('token_hash'));
        $this->assertGreaterThanOrEqual(2_592_000, (int) now()->diffInSeconds($credentials->accessToken->expires_at));
        $this->assertGreaterThanOrEqual(2_592_000, (int) now()->diffInSeconds($credentials->refreshToken->expires_at));
        $this->assertNotNull($credentials->securitySession->expires_at);
        $this->assertGreaterThanOrEqual(2_592_000, (int) now()->diffInSeconds($credentials->securitySession->expires_at));
        $this->assertSame($credentials->device->getKey(), $credentials->securitySession->device_id);
        $this->assertSame('security.mobile_credentials.issued', AuditEvent::query()->latest('id')->value('action'));
    }

    public function test_refresh_rotates_once_and_reuse_revokes_the_family_and_session(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);
        $credentials = $this->app->make(AuthenticateMobileLoginAction::class)->handle(
            $user->email,
            'correct-password',
            new RegisterDeviceData(identifier: 'installation-secret'),
        );

        $rotated = $this->app->make(RefreshMobileCredentialsAction::class)->handle(
            $credentials->plainRefreshToken,
            'installation-secret',
        );

        $this->assertNotSame($credentials->plainAccessToken, $rotated->plainAccessToken);
        $this->assertNotSame($credentials->plainRefreshToken, $rotated->plainRefreshToken);
        $this->assertSame($credentials->refreshToken->family_id, $rotated->refreshToken->family_id);
        $this->assertNotNull($credentials->refreshToken->fresh()->used_at);
        $this->assertSame($rotated->refreshToken->getKey(), $credentials->refreshToken->fresh()->replaced_by_id);
        $this->assertSame(
            $credentials->securitySession->expires_at?->utc()->toIso8601String(),
            $rotated->securitySession->fresh()->expires_at?->utc()->toIso8601String(),
        );

        try {
            $this->app->make(RefreshMobileCredentialsAction::class)->handle(
                $credentials->plainRefreshToken,
                'installation-secret',
            );
            $this->fail('Expected reused refresh credentials to be rejected.');
        } catch (AuthenticationException) {
            $this->assertNotNull($credentials->securitySession->fresh()->revoked_at);
        }

        $familyId = $credentials->accessToken->family_id;
        $this->assertSame(0, MobileAccessToken::query()->where('family_id', $familyId)->whereNull('revoked_at')->count());
        $this->assertSame(0, MobileRefreshToken::query()->where('family_id', $familyId)->whereNull('revoked_at')->count());
        $this->assertTrue(AuditEvent::query()->where('action', 'security.mobile_refresh.reuse_detected')->exists());
    }

    public function test_refresh_rejects_a_different_device_identifier_without_rotating(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);
        $credentials = $this->app->make(AuthenticateMobileLoginAction::class)->handle(
            $user->email,
            'correct-password',
            new RegisterDeviceData(identifier: 'correct-installation'),
        );

        try {
            $this->app->make(RefreshMobileCredentialsAction::class)->handle(
                $credentials->plainRefreshToken,
                'attacker-installation',
            );
            $this->fail('Expected the cross-device refresh to be rejected.');
        } catch (AuthenticationException) {
            $this->assertNull($credentials->refreshToken->fresh()->used_at);
        }

        $this->assertSame(1, MobileRefreshToken::query()->count());
    }

    public function test_device_revocation_invalidates_every_session_and_credential(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);
        $credentials = $this->app->make(AuthenticateMobileLoginAction::class)->handle(
            $user->email,
            'correct-password',
            new RegisterDeviceData(identifier: 'installation-secret'),
        );

        $revokedDevice = $this->app->make(RevokeDeviceAction::class)->handle(
            $credentials->device,
            'user_requested',
            $user,
        );

        $this->assertNotNull($revokedDevice->revoked_at);
        $this->assertNotNull($credentials->securitySession->fresh()->revoked_at);
        $this->assertNotNull($credentials->accessToken->fresh()->revoked_at);
        $this->assertNotNull($credentials->refreshToken->fresh()->revoked_at);
    }
}
