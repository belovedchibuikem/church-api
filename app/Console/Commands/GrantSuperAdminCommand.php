<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\AuthorizationBundleCatalog;
use App\Support\Authorization\ScopeReference;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

#[Signature('authorization:grant-super-admin {email : The user email address} {--force : Run in production without confirmation}')]
#[Description('Assign every admin authorization bundle at global platform scope to a user')]
class GrantSuperAdminCommand extends Command
{
    use ConfirmableTrait;

    public function handle(
        AssignRoleToUserAction $assignRole,
        AssignScopeToRoleAssignmentAction $assignScope,
    ): int {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $email = strtolower(trim((string) $this->argument('email')));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user === null) {
            $this->components->error("No user found with email {$email}.");

            return self::FAILURE;
        }

        $scope = new ScopeReference('global', 'platform');
        $assigned = 0;
        $skipped = 0;

        foreach (array_keys(AuthorizationBundleCatalog::BUNDLES) as $code) {
            $role = Role::query()->where('code', $code)->first();
            if ($role === null) {
                $this->components->warn("Role {$code} is missing. Run authorization:install-foundation first.");
                continue;
            }

            $existing = $user->roleAssignments()
                ->where('role_id', $role->getKey())
                ->whereHas('scopeAssignments', fn ($query) => $query
                    ->where('scope_type', $scope->type)
                    ->where('scope_key', $scope->key))
                ->exists();

            if ($existing) {
                $skipped++;
                continue;
            }

            $assignment = $assignRole->handle($user, $role);
            $assignScope->handle($assignment, $scope);
            $assigned++;
        }

        $this->components->info(sprintf(
            'Granted super-admin access to %s (%s): %d role assignments added, %d already present.',
            $user->name ?? $email,
            $email,
            $assigned,
            $skipped,
        ));

        return self::SUCCESS;
    }
}
