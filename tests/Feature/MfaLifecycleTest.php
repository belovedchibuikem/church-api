<?php

namespace Tests\Feature;

use App\Models\MfaRecoveryCode;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Security\ConfirmTotpEnrollmentAction;
use App\Support\Security\CreateTotpEnrollmentAction;
use App\Support\Security\RecordSecuritySessionAction;
use App\Support\Security\RegisterDeviceAction;
use App\Support\Security\RegisterDeviceData;
use App\Support\Security\TotpService;
use App\Support\Security\VerifyMfaChallengeAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MfaLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_totp_enrollment_encrypts_the_secret_hashes_recovery_codes_and_records_session_evidence(): void
    {
        $this->freezeSecond();
        [$user, $securitySession] = $this->userAndSession();
        $enrollment = $this->app->make(CreateTotpEnrollmentAction::class)->handle(
            $user,
            $securitySession,
            'Primary authenticator',
        );

        $this->assertSame($enrollment->secret, $enrollment->method->encrypted_secret);
        $this->assertNotSame($enrollment->secret, $enrollment->method->getRawOriginal('encrypted_secret'));
        $this->assertArrayNotHasKey('encrypted_secret', $enrollment->method->toArray());
        $this->assertCount(10, $enrollment->recoveryCodes);
        $this->assertSame(10, MfaRecoveryCode::query()->count());
        $this->assertTrue(Hash::check($enrollment->recoveryCodes[0], MfaRecoveryCode::query()->firstOrFail()->code_hash));

        $code = $this->app->make(TotpService::class)->codeAtCounter(
            $enrollment->secret,
            intdiv(now()->timestamp, 30),
        );
        $method = $this->app->make(ConfirmTotpEnrollmentAction::class)->handle(
            $user,
            $securitySession,
            $enrollment->method->public_id,
            $code,
        );

        $this->assertNotNull($method->verified_at);
        $this->assertSame($method->getKey(), $securitySession->fresh()->mfa_method_id);
        $this->assertTrue($securitySession->fresh()->mfa_verified_at->equalTo(now()));
    }

    public function test_totp_codes_cannot_be_replayed_and_recovery_codes_are_single_use(): void
    {
        $this->freezeSecond();
        [$user, $securitySession] = $this->userAndSession();
        $enrollment = $this->app->make(CreateTotpEnrollmentAction::class)->handle($user, $securitySession);
        $totp = $this->app->make(TotpService::class);
        $initialCode = $totp->codeAtCounter($enrollment->secret, intdiv(now()->timestamp, 30));
        $this->app->make(ConfirmTotpEnrollmentAction::class)->handle(
            $user,
            $securitySession,
            $enrollment->method->public_id,
            $initialCode,
        );
        $verify = $this->app->make(VerifyMfaChallengeAction::class);

        try {
            $verify->handle($user, $securitySession, $initialCode, null, $enrollment->method->public_id);
            $this->fail('Expected the TOTP replay to be rejected.');
        } catch (ValidationException) {
            $this->assertSame(0, MfaRecoveryCode::query()->whereNotNull('used_at')->count());
        }

        $verify->handle(
            $user,
            $securitySession,
            null,
            $enrollment->recoveryCodes[0],
            $enrollment->method->public_id,
        );

        $this->assertSame(1, MfaRecoveryCode::query()->whereNotNull('used_at')->count());

        $this->expectException(ValidationException::class);
        $verify->handle(
            $user,
            $securitySession,
            null,
            $enrollment->recoveryCodes[0],
            $enrollment->method->public_id,
        );
    }

    /** @return array{User, SecuritySession} */
    private function userAndSession(): array
    {
        $user = User::factory()->create();
        $device = $this->app->make(RegisterDeviceAction::class)->handle(
            $user,
            new RegisterDeviceData(identifier: 'installation-secret'),
            $user,
        );
        $securitySession = $this->app->make(RecordSecuritySessionAction::class)->handle(
            $user,
            $device,
            now()->addDay(),
            $user,
        );

        return [$user, $securitySession];
    }
}
