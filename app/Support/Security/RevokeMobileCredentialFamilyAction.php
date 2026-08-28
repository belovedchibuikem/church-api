<?php

namespace App\Support\Security;

use App\Models\MobileAccessToken;
use App\Models\MobileRefreshToken;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RevokeMobileCredentialFamilyAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(
        SecuritySession $securitySession,
        string $familyId,
        string $reason,
        ?User $actor = null,
    ): void {
        $this->validateReason($reason);

        DB::transaction(function () use ($securitySession, $familyId, $reason, $actor): void {
            $lockedSession = SecuritySession::query()->lockForUpdate()->findOrFail($securitySession->getKey());
            $revokedAt = now()->utc();

            MobileAccessToken::query()
                ->where('security_session_id', $lockedSession->getKey())
                ->where('family_id', $familyId)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => $revokedAt,
                    'revocation_reason' => $reason,
                    'updated_at' => $revokedAt,
                ]);

            MobileRefreshToken::query()
                ->where('security_session_id', $lockedSession->getKey())
                ->where('family_id', $familyId)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => $revokedAt,
                    'revocation_reason' => $reason,
                    'updated_at' => $revokedAt,
                ]);

            if ($lockedSession->revoked_at === null) {
                $lockedSession->forceFill([
                    'revoked_at' => $revokedAt,
                    'revocation_reason' => $reason,
                ])->save();
            }

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'security.mobile_credential_family.revoked',
                actor: $actor,
                targetType: 'security_session',
                targetId: $lockedSession->public_id,
                metadata: [
                    'credential_family_id' => $familyId,
                    'reason' => $reason,
                ],
            ));
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
