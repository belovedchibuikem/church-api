<?php

namespace App\Support\Identity;

use App\Models\Role;
use App\Models\User;
use App\Queries\Admin\ListScopedUsersQuery;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AuthorizationBundleCatalog;
use App\Support\Authorization\ScopeReference;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ProvisionAdminUserAction
{
    public function __construct(
        private RegisterBrowserUserAction $register,
        private AssignRoleToUserAction $assignRole,
        private RecordAuditEventAction $recordAuditEvent,
        private ListScopedUsersQuery $users,
    ) {}

    /**
     * @param  array{
     *     email: string,
     *     profile: array{given_name: string, middle_name?: string|null, family_name: string, preferred_name?: string|null, country?: string|null, region?: string|null, locality?: string|null}
     * }  $attributes
     */
    public function handle(User $actor, ScopeReference $scope, array $attributes, ?string $rolePublicId = null): User
    {
        $password = Str::password(20);
        $user = $this->register->handle([
            'profile' => $attributes['profile'],
            'email' => $attributes['email'],
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        if ($rolePublicId !== null) {
            $role = Role::query()->where('public_id', $rolePublicId)->firstOrFail();
            if ($role->code === AuthorizationBundleCatalog::SUPER_ADMINISTRATOR_ROLE) {
                throw new AccessDeniedHttpException('Super-administrator may not be assigned through user provisioning.');
            }
            $this->assignRole->handle($user, $role, $actor);
        }

        $this->recordAuditEvent->handle(new AuditEventData(
            action: 'identity.user.provisioned',
            actor: $actor,
            targetType: 'user',
            targetId: $user->public_id,
            scopeType: $scope->type,
            scopeId: $scope->key,
        ));

        Password::sendResetLink(['email' => $user->email]);

        return $this->users->findOrFail($actor, $scope, $user->public_id);
    }
}
