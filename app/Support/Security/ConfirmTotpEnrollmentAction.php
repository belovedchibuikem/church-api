<?php

namespace App\Support\Security;

use App\Models\MfaMethod;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmTotpEnrollmentAction
{
    public function __construct(
        private TotpService $totp,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        User $user,
        ?SecuritySession $securitySession,
        string $methodPublicId,
        string $code,
    ): MfaMethod {
        return DB::transaction(function () use ($user, $securitySession, $methodPublicId, $code): MfaMethod {
            $method = MfaMethod::query()
                ->whereBelongsTo($user)
                ->where('public_id', $methodPublicId)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedSession = $securitySession === null
                ? null
                : SecuritySession::query()->lockForUpdate()->findOrFail($securitySession->getKey());

            if (
                ($lockedSession !== null && (
                    $lockedSession->user_id !== $user->getKey()
                    || $lockedSession->revoked_at !== null
                ))
                || $method->method_type !== 'totp'
                || $method->revoked_at !== null
                || $method->verified_at !== null
                || $method->encrypted_secret === null
            ) {
                throw new AuthorizationException;
            }

            $counter = $this->totp->matchingCounter($method->encrypted_secret, $code);

            if ($counter === null) {
                throw ValidationException::withMessages(['code' => ['The authenticator code is invalid.']]);
            }

            $verifiedAt = now()->utc();
            $method->forceFill([
                'verified_at' => $verifiedAt,
                'last_used_at' => $verifiedAt,
                'last_totp_counter' => $counter,
            ])->save();
            if ($lockedSession !== null) {
                $lockedSession->forceFill([
                    'mfa_method_id' => $method->getKey(),
                    'mfa_verified_at' => $verifiedAt,
                ])->save();
            }

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'security.mfa_totp.enrollment_confirmed',
                actor: $user,
                targetType: 'mfa_method',
                targetId: $method->public_id,
                metadata: ['security_session_public_id' => $lockedSession?->public_id],
            ));

            $method->setAttribute(
                'unused_recovery_codes_count',
                $method->recoveryCodes()->whereNull('used_at')->count(),
            );

            return $method;
        }, attempts: 3);
    }
}
