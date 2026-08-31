<?php

namespace App\Support\Identity;

use App\Models\User;
use App\Queries\Admin\ListScopedUsersQuery;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use App\Support\Authorization\ScopeReference;
use Illuminate\Support\Facades\Password;

class RequestAdminUserPasswordResetAction
{
    public function __construct(
        private ListScopedUsersQuery $users,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(User $actor, ScopeReference $scope, string $userPublicId): User
    {
        $target = $this->users->findOrFail($actor, $scope, $userPublicId);
        Password::sendResetLink(['email' => $target->email]);
        $this->recordAuditEvent->handle(new AuditEventData(
            action: 'identity.user.password_reset_requested',
            actor: $actor,
            targetType: 'user',
            targetId: $target->public_id,
        ));

        return $target;
    }
}
