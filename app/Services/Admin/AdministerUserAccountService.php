<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Queries\Admin\ListScopedUsersQuery;
use App\Support\Authorization\ScopeReference;
use App\Support\Identity\ReactivateUserAction;
use App\Support\Identity\SuspendUserAction;
use Illuminate\Support\Facades\Gate;

class AdministerUserAccountService
{
    public function __construct(
        private readonly ListScopedUsersQuery $users,
        private readonly SuspendUserAction $suspendUser,
        private readonly ReactivateUserAction $reactivateUser,
    ) {}

    public function suspend(User $actor, ScopeReference $scope, string $userPublicId, string $reason): User
    {
        $target = $this->users->findOrFail($actor, $scope, $userPublicId);
        Gate::forUser($actor)->authorize('suspend', $target);

        $this->suspendUser->handle($target, $reason, $actor);

        return $this->users->findOrFail($actor, $scope, $userPublicId);
    }

    public function reactivate(User $actor, ScopeReference $scope, string $userPublicId): User
    {
        $target = $this->users->findOrFail($actor, $scope, $userPublicId);
        Gate::forUser($actor)->authorize('reactivate', $target);

        $this->reactivateUser->handle($target, $actor);

        return $this->users->findOrFail($actor, $scope, $userPublicId);
    }
}
