<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Device;
use App\Models\SecuritySession;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProtectedUserApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_surface_requires_an_active_verified_identity(): void
    {
        $this->getJson('/api/v1/user/me')->assertUnauthorized();

        $unverifiedUser = User::factory()->withPerson()->unverified()->create();
        $this->authenticate($unverifiedUser);

        $this->getJson('/api/v1/user/me')->assertForbidden();
    }

    public function test_updates_preferences_and_manages_owned_consents(): void
    {
        $user = User::factory()->withPerson()->create();
        $this->authenticate($user, recentMfa: true);

        $this->putJson('/api/v1/user/preferences', [
            'locale' => 'en-NG',
            'timezone' => 'Africa/Lagos',
            'notification_channels' => ['email', 'in_app'],
        ])->assertOk()
            ->assertJsonPath('data.locale', 'en-NG')
            ->assertJsonPath('data.timezone', 'Africa/Lagos');

        $consentResponse = $this->postJson('/api/v1/user/consents', [
            'purpose' => 'communications.email',
            'policy_version' => '2026.08',
        ])->assertCreated()
            ->assertJsonPath('data.purpose', 'communications.email');
        $consentId = $consentResponse->json('data.id');

        $this->getJson('/api/v1/user/consents')
            ->assertOk()
            ->assertJsonPath('data.0.id', $consentId);
        $this->deleteJson("/api/v1/user/consents/{$consentId}")
            ->assertOk()
            ->assertJsonPath('data.withdrawn_at', fn (mixed $value): bool => is_string($value));

        $this->assertTrue(AuditEvent::query()->where('action', 'identity.preferences.updated')->exists());
        $this->assertTrue(AuditEvent::query()->where('action', 'privacy.consent.withdrawn')->exists());
    }

    public function test_updates_own_profile_after_recent_mfa(): void
    {
        $user = User::factory()->withPerson()->create();
        $this->authenticate($user);
        $this->flushSession();

        $this->putJson('/api/v1/user/profile', [
            'given_name' => 'Ada',
            'family_name' => 'Lovelace',
            'preferred_name' => 'Ada L.',
        ])->assertUnauthorized();

        $this->authenticate($user, recentMfa: true);

        $this->putJson('/api/v1/user/profile', [
            'given_name' => 'Ada',
            'middle_name' => 'Byron',
            'family_name' => 'Lovelace',
            'preferred_name' => 'Ada L.',
        ])->assertOk()
            ->assertJsonPath('data.profile.given_name', 'Ada')
            ->assertJsonPath('data.profile.middle_name', 'Byron')
            ->assertJsonPath('data.profile.family_name', 'Lovelace')
            ->assertJsonPath('data.profile.preferred_name', 'Ada L.');

        $this->assertTrue(AuditEvent::query()->where('action', 'identity.profile.updated')->exists());
        $this->flushSession();
    }

    public function test_security_inventory_and_revocation_are_mfa_and_ownership_scoped(): void
    {
        $user = User::factory()->withPerson()->create();
        $otherUser = User::factory()->withPerson()->create();
        $currentSession = $this->authenticate($user);
        $device = Device::factory()->for($user)->create();
        $managedSession = SecuritySession::factory()->for($user)->for($device)->create();
        $otherDevice = Device::factory()->for($otherUser)->create();

        $this->getJson('/api/v1/user/security/devices')->assertForbidden();

        $this->withSession([
            'security_session_id' => $currentSession->public_id,
            'auth.mfa_verified_at' => now()->utc()->toIso8601String(),
        ]);

        $this->getJson('/api/v1/user/security/devices')
            ->assertOk()
            ->assertJsonFragment(['id' => $device->public_id])
            ->assertJsonMissing(['id' => $otherDevice->public_id]);
        $this->deleteJson("/api/v1/user/security/devices/{$otherDevice->public_id}")
            ->assertNotFound();
        $this->deleteJson("/api/v1/user/security/sessions/{$managedSession->public_id}")
            ->assertOk()
            ->assertJsonPath('data.revoked_at', fn (mixed $value): bool => is_string($value));
        $this->deleteJson("/api/v1/user/security/devices/{$device->public_id}")
            ->assertOk()
            ->assertJsonPath('data.revoked_at', fn (mixed $value): bool => is_string($value));

        $this->assertNotNull($managedSession->fresh()->revoked_at);
        $this->assertNotNull($device->fresh()->revoked_at);
    }

    private function authenticate(User $user, bool $recentMfa = false): SecuritySession
    {
        $securitySession = SecuritySession::factory()->for($user)->create();
        $session = ['security_session_id' => $securitySession->public_id];

        if ($recentMfa) {
            $session['auth.mfa_verified_at'] = now()->utc()->toIso8601String();
        }

        $this->actingAs($user);
        $this->withSession($session);

        return $securitySession;
    }
}
