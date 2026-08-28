<?php

namespace App\Support\Security;

use App\Exceptions\SuspendedAccountException;
use App\Models\MfaMethod;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StoreMfaMethodAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    /**
     * @param  array<int, string>  $recoveryCodes
     */
    public function handle(
        User $user,
        string $methodType,
        string $secret,
        array $recoveryCodes = [],
        ?string $label = null,
        ?User $actor = null,
    ): MfaMethod {
        $this->validateInput($methodType, $secret, $recoveryCodes, $label);

        return DB::transaction(function () use (
            $user,
            $methodType,
            $secret,
            $recoveryCodes,
            $label,
            $actor,
        ): MfaMethod {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($lockedUser->isSuspended()) {
                throw new SuspendedAccountException;
            }

            $mfaMethod = MfaMethod::query()->create([
                'user_id' => $lockedUser->getKey(),
                'method_type' => $methodType,
                'label' => $label,
                'secret_hash' => Hash::make($secret),
            ]);

            foreach ($recoveryCodes as $recoveryCode) {
                $mfaMethod->recoveryCodes()->create([
                    'code_hash' => Hash::make($recoveryCode),
                ]);
            }

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'security.mfa_method.stored',
                actor: $actor,
                targetType: 'mfa_method',
                targetId: $mfaMethod->public_id,
                metadata: [
                    'method_type' => $methodType,
                    'recovery_code_count' => count($recoveryCodes),
                ],
            ));

            return $mfaMethod->load('recoveryCodes');
        }, attempts: 3);
    }

    /**
     * @param  array<int, string>  $recoveryCodes
     */
    private function validateInput(
        string $methodType,
        string $secret,
        array $recoveryCodes,
        ?string $label,
    ): void {
        if (
            Str::length($methodType) > 50
            || ! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $methodType)
        ) {
            throw new InvalidArgumentException('The MFA method type must be a stable lowercase identifier.');
        }

        if ($secret === '' || Str::length($secret) > 4096) {
            throw new InvalidArgumentException('The MFA secret must contain between 1 and 4096 characters.');
        }

        if ($label !== null && Str::length($label) > 100) {
            throw new InvalidArgumentException('The MFA method label is too long.');
        }

        if (array_values(array_unique($recoveryCodes)) !== $recoveryCodes) {
            throw new InvalidArgumentException('MFA recovery codes must be unique.');
        }

        foreach ($recoveryCodes as $recoveryCode) {
            if ($recoveryCode === '' || Str::length($recoveryCode) > 255) {
                throw new InvalidArgumentException('MFA recovery codes must contain between 1 and 255 characters.');
            }
        }
    }
}
