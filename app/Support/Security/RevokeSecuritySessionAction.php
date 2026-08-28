<?php

namespace App\Support\Security;

use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RevokeSecuritySessionAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        SecuritySession $securitySession,
        string $reason,
        ?User $actor = null,
    ): SecuritySession {
        $this->validateReason($reason);

        return DB::transaction(function () use ($securitySession, $reason, $actor): SecuritySession {
            $lockedSession = SecuritySession::query()
                ->lockForUpdate()
                ->findOrFail($securitySession->getKey());

            if ($lockedSession->revoked_at !== null) {
                return $lockedSession;
            }

            $lockedSession->forceFill([
                'revoked_at' => now()->utc(),
                'revocation_reason' => $reason,
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'security.session.revoked',
                actor: $actor,
                targetType: 'security_session',
                targetId: $lockedSession->public_id,
                metadata: ['reason' => $reason],
            ));

            return $lockedSession;
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
