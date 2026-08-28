<?php

namespace App\Support\Security;

use App\Models\MfaMethod;
use App\Models\MfaRecoveryCode;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class VerifyMfaChallengeAction
{
    public function __construct(
        private TotpService $totp,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        User $user,
        ?SecuritySession $securitySession,
        ?string $code,
        ?string $recoveryCode,
        ?string $methodPublicId = null,
    ): ?SecuritySession {
        return DB::transaction(function () use (
            $user,
            $securitySession,
            $code,
            $recoveryCode,
            $methodPublicId,
        ): ?SecuritySession {
            $lockedSession = $securitySession === null
                ? null
                : SecuritySession::query()->lockForUpdate()->findOrFail($securitySession->getKey());

            if ($lockedSession !== null && (
                $lockedSession->user_id !== $user->getKey()
                || $lockedSession->revoked_at !== null
            )) {
                throw new AuthorizationException;
            }

            $methodQuery = MfaMethod::query()
                ->whereBelongsTo($user)
                ->where('method_type', 'totp')
                ->whereNotNull('verified_at')
                ->whereNull('revoked_at');

            if ($methodPublicId !== null) {
                $methodQuery->where('public_id', $methodPublicId);
            }

            $method = $methodQuery->latest('verified_at')->lockForUpdate()->firstOrFail();
            $factorType = $code !== null
                ? $this->verifyTotp($method, $code)
                : $this->consumeRecoveryCode($method, (string) $recoveryCode);
            $verifiedAt = now()->utc();

            $method->forceFill(['last_used_at' => $verifiedAt])->save();
            if ($lockedSession !== null) {
                $lockedSession->forceFill([
                    'mfa_method_id' => $method->getKey(),
                    'mfa_verified_at' => $verifiedAt,
                ])->save();
            }

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'security.mfa.challenge_verified',
                actor: $user,
                targetType: $lockedSession === null ? 'mfa_method' : 'security_session',
                targetId: $lockedSession?->public_id ?? $method->public_id,
                metadata: [
                    'mfa_method_public_id' => $method->public_id,
                    'factor_type' => $factorType,
                ],
            ));

            return $lockedSession;
        }, attempts: 3);
    }

    private function verifyTotp(MfaMethod $method, string $code): string
    {
        if ($method->encrypted_secret === null) {
            throw new AuthorizationException;
        }

        $counter = $this->totp->matchingCounter($method->encrypted_secret, $code);

        if ($counter === null || ($method->last_totp_counter !== null && $counter <= $method->last_totp_counter)) {
            throw ValidationException::withMessages(['code' => ['The MFA challenge is invalid or has already been used.']]);
        }

        $method->forceFill(['last_totp_counter' => $counter]);

        return 'totp';
    }

    private function consumeRecoveryCode(MfaMethod $method, string $plainRecoveryCode): string
    {
        /** @var Collection<int, MfaRecoveryCode> $recoveryCodes */
        $recoveryCodes = $method->recoveryCodes()
            ->whereNull('used_at')
            ->lockForUpdate()
            ->get();

        $matchedRecoveryCode = $recoveryCodes->first(
            fn (MfaRecoveryCode $candidate): bool => Hash::check($plainRecoveryCode, $candidate->code_hash),
        );

        if ($matchedRecoveryCode === null) {
            throw ValidationException::withMessages(['recovery_code' => ['The MFA challenge is invalid or has already been used.']]);
        }

        $matchedRecoveryCode->forceFill(['used_at' => now()->utc()])->save();

        return 'recovery_code';
    }
}
