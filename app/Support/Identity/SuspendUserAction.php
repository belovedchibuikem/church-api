<?php

namespace App\Support\Identity;

use App\Exceptions\UserAccountStateConflictException;
use App\Identity\UserAccountStatus;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use App\Support\Authorization\AuthorizationBundleCatalog;
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

            $this->guardLastSuperAdministrator($lockedUser);

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

    private function guardLastSuperAdministrator(User $user): void
    {
        $isSuper = $user->roleAssignments()
            ->active()
            ->whereHas('role', fn ($query) => $query->where('code', AuthorizationBundleCatalog::SUPER_ADMINISTRATOR_ROLE))
            ->exists();

        if (! $isSuper) {
            return;
        }

        $remaining = User::query()
            ->whereKeyNot($user->getKey())
            ->where('account_status', UserAccountStatus::Active)
            ->whereHas(
                'roleAssignments',
                fn ($query) => $query->active()->whereHas(
                    'role',
                    fn ($roleQuery) => $roleQuery->where('code', AuthorizationBundleCatalog::SUPER_ADMINISTRATOR_ROLE),
                ),
            )
            ->count();

        if ($remaining === 0) {
            throw new UserAccountStateConflictException('The last super-administrator cannot be suspended.');
        }
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
