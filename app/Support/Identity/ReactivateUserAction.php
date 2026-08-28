<?php

namespace App\Support\Identity;

use App\Identity\UserAccountStatus;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class ReactivateUserAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(User $user, User $actor): User
    {
        return DB::transaction(function () use ($user, $actor): User {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (! $lockedUser->isSuspended()) {
                return $lockedUser;
            }

            $previousSuspensionReason = $lockedUser->suspension_reason;
            $lockedUser->account_status = UserAccountStatus::Active;
            $lockedUser->suspension_reason = null;
            $lockedUser->reactivated_at = now()->utc();
            $lockedUser->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'identity.user.reactivated',
                actor: $actor,
                targetType: 'user',
                targetId: (string) $lockedUser->getKey(),
                metadata: ['previous_suspension_reason' => $previousSuspensionReason],
            ));

            return $lockedUser;
        }, attempts: 3);
    }
}
