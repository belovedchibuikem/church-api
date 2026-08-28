<?php

namespace App\Support\Identity;

use App\Exceptions\UserAccountStateConflictException;
use App\Identity\UserAccountStatus;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SuspendUserAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(User $user, string $reason, User $actor): User
    {
        $this->validateReason($reason);

        return DB::transaction(function () use ($user, $reason, $actor): User {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($lockedUser->isSuspended()) {
                if ($lockedUser->suspension_reason === $reason) {
                    return $lockedUser;
                }

                throw new UserAccountStateConflictException(
                    'The account is already suspended for a different reason.',
                );
            }

            $lockedUser->account_status = UserAccountStatus::Suspended;
            $lockedUser->suspension_reason = $reason;
            $lockedUser->suspended_at = now()->utc();
            $lockedUser->reactivated_at = null;
            $lockedUser->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'identity.user.suspended',
                actor: $actor,
                targetType: 'user',
                targetId: (string) $lockedUser->getKey(),
                metadata: ['reason' => $reason],
            ));

            return $lockedUser;
        }, attempts: 3);
    }

    private function validateReason(string $reason): void
    {
        if (
            Str::length($reason) > 191
            || ! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $reason)
        ) {
            throw new InvalidArgumentException('The suspension reason must be a stable lowercase code.');
        }
    }
}
