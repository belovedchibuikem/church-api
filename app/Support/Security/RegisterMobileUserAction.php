<?php

namespace App\Support\Security;

use App\Models\Role;
use App\Models\User;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\AuthorizationBundleCatalog;
use App\Support\Authorization\ScopeReference;
use App\Support\Identity\RegisterBrowserUserAction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RegisterMobileUserAction
{
    public function __construct(
        private RegisterBrowserUserAction $registerUser,
        private AssignRoleToUserAction $assignRole,
        private AssignScopeToRoleAssignmentAction $assignScope,
        private RegisterDeviceAction $registerDevice,
        private RecordSecuritySessionAction $recordSecuritySession,
        private IssueMobileCredentialsAction $issueCredentials,
    ) {}

    /**
     * @param array{
     *     profile: array{
     *         given_name: string,
     *         middle_name?: string|null,
     *         family_name: string,
     *         preferred_name?: string|null,
     *         country?: string|null,
     *         region?: string|null,
     *         locality?: string|null
     *     },
     *     email: string,
     *     password: string,
     *     password_confirmation: string
     * } $attributes
     */
    public function handle(
        #[\SensitiveParameter] array $attributes,
        RegisterDeviceData $deviceData,
    ): IssuedMobileCredentials {
        try {
            $user = $this->registerUser->handle($attributes);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1062) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'email' => ['An account cannot be created with the supplied details.'],
            ]);
        }

        return DB::transaction(function () use ($user, $deviceData): IssuedMobileCredentials {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($lockedUser->email_verified_at === null) {
                $lockedUser->forceFill(['email_verified_at' => now()->utc()])->save();
            }

            $this->grantMemberAccess($lockedUser);

            $device = $this->registerDevice->handle($lockedUser, $deviceData, $lockedUser);
            $network = ClientNetworkContext::fromRequest();
            $securitySession = $this->recordSecuritySession->handle(
                user: $lockedUser,
                device: $device,
                expiresAt: MobileCredentialTtl::sessionExpiresAt(),
                actor: $lockedUser,
                ipAddress: $network['ip'],
                countryCode: $network['country'],
            );

            return $this->issueCredentials->handle($lockedUser, $device, $securitySession);
        }, attempts: 3);
    }

    private function grantMemberAccess(User $user): void
    {
        $role = Role::query()
            ->where('code', AuthorizationBundleCatalog::MEMBER_SECURITY_ROLE)
            ->first();

        if ($role === null) {
            throw new RuntimeException(
                'Member security role is not provisioned. Run authorization seeders before mobile registration.',
            );
        }

        $assignment = $this->assignRole->handle($user, $role, $user);
        $this->assignScope->handle($assignment, new ScopeReference('global', 'platform'), $user);
    }
}
