<?php

namespace App\Support\Security;

use App\Exceptions\SuspendedAccountException;
use App\Models\MfaMethod;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateTotpEnrollmentAction
{
    public function __construct(
        private TotpService $totp,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(User $user, ?SecuritySession $securitySession, ?string $label = null): TotpEnrollmentResult
    {
        return DB::transaction(function () use ($user, $securitySession, $label): TotpEnrollmentResult {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $lockedSession = $securitySession === null
                ? null
                : SecuritySession::query()->lockForUpdate()->findOrFail($securitySession->getKey());

            if ($lockedUser->isSuspended()) {
                throw new SuspendedAccountException;
            }

            if ($lockedSession !== null && (
                $lockedSession->user_id !== $lockedUser->getKey()
                || $lockedSession->revoked_at !== null
            )) {
                throw new AuthorizationException;
            }

            MfaMethod::query()
                ->whereBelongsTo($lockedUser)
                ->where('method_type', 'totp')
                ->whereNull('verified_at')
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now()->utc(),
                    'revocation_reason' => 'superseded',
                    'updated_at' => now()->utc(),
                ]);

            $secret = $this->totp->generateSecret();
            $recoveryCodes = $this->generateRecoveryCodes();
            $method = MfaMethod::query()->create([
                'user_id' => $lockedUser->getKey(),
                'method_type' => 'totp',
                'label' => $label,
                'secret_hash' => null,
                'encrypted_secret' => $secret,
            ]);

            foreach ($recoveryCodes as $recoveryCode) {
                $method->recoveryCodes()->create(['code_hash' => Hash::make($recoveryCode)]);
            }

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'security.mfa_totp.enrollment_started',
                actor: $lockedUser,
                targetType: 'mfa_method',
                targetId: $method->public_id,
                metadata: ['recovery_code_count' => count($recoveryCodes)],
            ));

            return new TotpEnrollmentResult(
                method: $method,
                secret: $secret,
                provisioningUri: $this->totp->provisioningUri($secret, $lockedUser->email),
                recoveryCodes: $recoveryCodes,
            );
        }, attempts: 3);
    }

    /** @return array<int, string> */
    private function generateRecoveryCodes(): array
    {
        return collect(range(1, 10))
            ->map(fn (): string => Str::lower(Str::random(5).'-'.Str::random(5).'-'.Str::random(5)))
            ->all();
    }
}
