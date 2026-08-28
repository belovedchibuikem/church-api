<?php

namespace App\Support\Security;

use App\Models\MfaMethod;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RevokeMfaMethodAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(MfaMethod $mfaMethod, string $reason, ?User $actor = null): MfaMethod
    {
        $this->validateReason($reason);

        return DB::transaction(function () use ($mfaMethod, $reason, $actor): MfaMethod {
            $lockedMethod = MfaMethod::query()->lockForUpdate()->findOrFail($mfaMethod->getKey());

            if ($lockedMethod->revoked_at !== null) {
                return $lockedMethod;
            }

            $lockedMethod->forceFill([
                'revoked_at' => now()->utc(),
                'revocation_reason' => $reason,
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'security.mfa_method.revoked',
                actor: $actor,
                targetType: 'mfa_method',
                targetId: $lockedMethod->public_id,
                metadata: ['reason' => $reason],
            ));

            return $lockedMethod;
        }, attempts: 3);
    }

    private function validateReason(string $reason): void
    {
        if (
            Str::length($reason) > 100
            || ! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $reason)
        ) {
            throw new InvalidArgumentException('The revocation reason must be a stable lowercase identifier.');
        }
    }
}
