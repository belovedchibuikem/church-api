<?php

namespace Tests\Feature;

use App\Exceptions\DeviceOwnershipException;
use App\Exceptions\RevokedDeviceException;
use App\Exceptions\SuspendedAccountException;
use App\Models\AuditEvent;
use App\Models\Device;
use App\Models\MfaMethod;
use App\Models\MfaRecoveryCode;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Audit\RecordAuditEventAction;
use App\Support\Security\RecordSecuritySessionAction;
use App\Support\Security\RegisterDeviceAction;
use App\Support\Security\RegisterDeviceData;
use App\Support\Security\RevokeMfaMethodAction;
use App\Support\Security\RevokeSecuritySessionAction;
use App\Support\Security\StoreMfaMethodAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class SecurityPersistenceFoundationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_registers_hashed_device_metadata_idempotently_and_records_audit(): void
    {
        $this->freezeSecond();
        $user = User::factory()->create();
        $action = $this->app->make(RegisterDeviceAction::class);
        $identifier = 'install-identifier-that-must-not-be-stored';

        $device = $action->handle($user, new RegisterDeviceData(
            identifier: $identifier,
            label: 'Personal phone',
            deviceType: 'phone',
            platform: 'Android',
            appVersion: '1.2.3',
        ), $user);
        $sameDevice = $action->handle($user, new RegisterDeviceData(
            identifier: $identifier,
            label: 'Renamed phone',
            deviceType: 'phone',
            platform: 'Android',
            appVersion: '1.2.4',
        ), $user);
        $unchangedDevice = $action->handle($user, new RegisterDeviceData(
            identifier: $identifier,
            label: 'Renamed phone',
            deviceType: 'phone',
            platform: 'Android',
            appVersion: '1.2.4',
        ), $user);

        $this->assertModelExists($device);
        $this->assertTrue(Str::isUlid($device->public_id));
        $this->assertSame($device->getKey(), $sameDevice->getKey());
        $this->assertSame($device->getKey(), $unchangedDevice->getKey());
        $this->assertSame('Renamed phone', $sameDevice->label);
        $this->assertSame(
            hash_hmac('sha256', $identifier, (string) config('app.key')),
            $sameDevice->identifier_hash,
        );
        $this->assertArrayNotHasKey('identifier_hash', $sameDevice->toArray());
        $this->assertSame(1, Device::query()->count());

        $auditEvents = AuditEvent::query()->orderBy('id')->get();
        $this->assertSame([
            'security.device.registered',
            'security.device.metadata_updated',
        ], $auditEvents->pluck('action')->all());
        $this->assertSame($device->public_id, $auditEvents->first()->target_id);
        $this->assertSame($user->getKey(), $auditEvents->first()->actor_user_id);
    }

    public function test_rejects_re_registering_a_revoked_device_without_reactivating_it(): void
    {
        $user = User::factory()->create();
        $action = $this->app->make(RegisterDeviceAction::class);
        $data = new RegisterDeviceData(identifier: 'revoked-install-identifier');
        $device = $action->handle($user, $data, $user);
        $device->forceFill([
            'revoked_at' => now(),
            'revocation_reason' => 'user_requested',
        ])->save();
        $wasRejected = false;

        try {
            $action->handle($user, $data, $user);
            $this->fail('Expected the revoked device to remain revoked.');
        } catch (RevokedDeviceException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertNotNull($device->fresh()->revoked_at);
        $this->assertSame(1, AuditEvent::query()->count());
    }

    public function test_rolls_back_a_sensitive_mutation_when_audit_recording_fails(): void
    {
        $user = User::factory()->create();
        $this->mock(RecordAuditEventAction::class)
            ->shouldReceive('handle')
            ->once()
            ->andThrow(new RuntimeException('Audit persistence unavailable.'));
        $wasRolledBack = false;

        try {
            $this->app->make(RegisterDeviceAction::class)->handle(
                $user,
                new RegisterDeviceData(identifier: 'transactional-device'),
                $user,
            );
            $this->fail('Expected audit failure to roll back device registration.');
        } catch (RuntimeException) {
            $wasRolledBack = true;
        }

        $this->assertTrue($wasRolledBack);
        $this->assertSame(0, Device::query()->count());
    }

    public function test_records_and_idempotently_revokes_a_security_session_with_audit(): void
    {
        $this->freezeSecond();
        $user = User::factory()->create();
        $device = Device::factory()->for($user)->create();
        $record = $this->app->make(RecordSecuritySessionAction::class);
        $revoke = $this->app->make(RevokeSecuritySessionAction::class);

        $securitySession = $record->handle($user, $device, now()->addHour(), $user);

        $this->assertModelExists($securitySession);
        $this->assertTrue(Str::isUlid($securitySession->public_id));
        $this->assertSame($user->getKey(), $securitySession->user_id);
        $this->assertSame($device->getKey(), $securitySession->device_id);
        $this->assertSame(1, SecuritySession::query()->usable()->count());

        $revokedSession = $revoke->handle($securitySession, 'user_requested', $user);
        $sameRevokedSession = $revoke->handle($securitySession, 'user_requested', $user);

        $this->assertNotNull($revokedSession->revoked_at);
        $this->assertSame('user_requested', $revokedSession->revocation_reason);
        $this->assertSame($revokedSession->getKey(), $sameRevokedSession->getKey());
        $this->assertSame(0, SecuritySession::query()->usable()->count());
        $this->assertSame(
            ['security.session.recorded', 'security.session.revoked'],
            AuditEvent::query()->orderBy('occurred_at')->orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_rejects_a_session_for_another_users_device(): void
    {
        $sessionOwner = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherDevice = Device::factory()->for($otherUser)->create();
        $wasRejected = false;

        try {
            $this->app->make(RecordSecuritySessionAction::class)
                ->handle($sessionOwner, $otherDevice, now()->addHour(), $sessionOwner);
            $this->fail('Expected the cross-user device to be rejected.');
        } catch (DeviceOwnershipException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame(0, SecuritySession::query()->count());
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_stores_only_hashes_for_mfa_and_recovery_secrets(): void
    {
        $user = User::factory()->create();
        $secret = 'mfa-enrollment-secret';
        $recoveryCodes = ['recover-one', 'recover-two'];

        $mfaMethod = $this->app->make(StoreMfaMethodAction::class)->handle(
            user: $user,
            methodType: 'authenticator_secret',
            secret: $secret,
            recoveryCodes: $recoveryCodes,
            label: 'Primary authenticator',
            actor: $user,
        );

        $this->assertModelExists($mfaMethod);
        $this->assertTrue(Str::isUlid($mfaMethod->public_id));
        $this->assertTrue(Hash::check($secret, $mfaMethod->secret_hash));
        $this->assertNotSame($secret, $mfaMethod->getRawOriginal('secret_hash'));
        $this->assertArrayNotHasKey('secret_hash', $mfaMethod->toArray());
        $this->assertCount(2, $mfaMethod->recoveryCodes);

        foreach ($mfaMethod->recoveryCodes as $index => $recoveryCode) {
            $this->assertTrue(Str::isUlid($recoveryCode->public_id));
            $this->assertTrue(Hash::check($recoveryCodes[$index], $recoveryCode->code_hash));
            $this->assertNotSame($recoveryCodes[$index], $recoveryCode->getRawOriginal('code_hash'));
            $this->assertArrayNotHasKey('code_hash', $recoveryCode->toArray());
        }

        $auditEvent = AuditEvent::query()->sole();
        $this->assertSame('security.mfa_method.stored', $auditEvent->action);
        $this->assertSame([
            'method_type' => 'authenticator_secret',
            'recovery_code_count' => 2,
        ], $auditEvent->metadata);
        $this->assertStringNotContainsString($secret, json_encode($auditEvent->metadata, JSON_THROW_ON_ERROR));
    }

    public function test_revoked_or_unverified_mfa_methods_are_not_usable(): void
    {
        $user = User::factory()->create();
        $activeMethod = MfaMethod::factory()->for($user)->create();
        MfaMethod::factory()->for($user)->unverified()->create();

        $this->assertSame([$activeMethod->getKey()], MfaMethod::query()->usable()->pluck('id')->all());

        $revokedMethod = $this->app->make(RevokeMfaMethodAction::class)
            ->handle($activeMethod, 'user_requested', $user);

        $this->assertNotNull($revokedMethod->revoked_at);
        $this->assertSame(0, MfaMethod::query()->usable()->count());
        $this->assertSame('security.mfa_method.revoked', AuditEvent::query()->sole()->action);
    }

    public function test_suspended_account_cannot_register_a_device(): void
    {
        $user = User::factory()->suspended()->create();
        $wasRejected = false;

        try {
            $this->app->make(RegisterDeviceAction::class)->handle(
                $user,
                new RegisterDeviceData(identifier: 'new-device'),
                $user,
            );
            $this->fail('Expected the suspended account device registration to be rejected.');
        } catch (SuspendedAccountException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame(0, Device::query()->count());
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_suspended_account_cannot_record_a_security_session(): void
    {
        $user = User::factory()->suspended()->create();
        $existingDevice = Device::factory()->for($user)->create();
        $wasRejected = false;

        try {
            $this->app->make(RecordSecuritySessionAction::class)->handle(
                $user,
                $existingDevice,
                now()->addHour(),
                $user,
            );
            $this->fail('Expected the suspended account session to be rejected.');
        } catch (SuspendedAccountException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame(0, SecuritySession::query()->count());
        $this->assertSame(0, AuditEvent::query()->count());
        $this->assertSame(0, Device::query()->usable()->count());
    }

    public function test_suspended_account_cannot_store_an_mfa_method(): void
    {
        $user = User::factory()->suspended()->create();
        $wasRejected = false;

        try {
            $this->app->make(StoreMfaMethodAction::class)->handle(
                user: $user,
                methodType: 'authenticator_secret',
                secret: 'secret',
                actor: $user,
            );
            $this->fail('Expected the suspended account MFA method to be rejected.');
        } catch (SuspendedAccountException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame(0, MfaMethod::query()->count());
        $this->assertSame(0, MfaRecoveryCode::query()->count());
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_revocation_state_cannot_be_mass_assigned(): void
    {
        $device = Device::factory()->create();
        $securitySession = SecuritySession::factory()->create();
        $mfaMethod = MfaMethod::factory()->create();
        $recoveryCode = MfaRecoveryCode::factory()->create();

        $device->fill(['revoked_at' => now(), 'revocation_reason' => 'attacker_supplied']);
        $securitySession->fill(['revoked_at' => now(), 'revocation_reason' => 'attacker_supplied']);
        $mfaMethod->fill([
            'verified_at' => now(),
            'revoked_at' => now(),
            'revocation_reason' => 'attacker_supplied',
        ]);
        $recoveryCode->fill(['used_at' => now()]);

        $this->assertFalse($device->isDirty('revoked_at'));
        $this->assertFalse($device->isDirty('revocation_reason'));
        $this->assertFalse($securitySession->isDirty('revoked_at'));
        $this->assertFalse($securitySession->isDirty('revocation_reason'));
        $this->assertFalse($mfaMethod->isDirty('revoked_at'));
        $this->assertFalse($mfaMethod->isDirty('revocation_reason'));
        $this->assertFalse($mfaMethod->isDirty('verified_at'));
        $this->assertFalse($recoveryCode->isDirty('used_at'));
    }
}
