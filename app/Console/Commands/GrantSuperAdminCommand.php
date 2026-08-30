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

        return $this->grantByEmail($email, $this->output);
    }

    public function grantByEmail(string $email, $output = null): int
    {
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user === null) {
            ($output ?? $this->output)->writeln("<error>No user found with email {$email}.</error>");

            return self::FAILURE;
        }

        $scope = new ScopeReference('global', 'platform');
        $assigned = 0;
        $skipped = 0;

        $superRole = Role::query()->where('code', AuthorizationBundleCatalog::SUPER_ADMINISTRATOR_ROLE)->first();
        if ($superRole !== null) {
            $existing = $user->roleAssignments()
                ->where('role_id', $superRole->getKey())
                ->whereHas('scopeAssignments', fn ($query) => $query
                    ->where('scope_type', $scope->type)
                    ->where('scope_key', $scope->key))
                ->exists();

            if ($existing) {
                $skipped++;
            } else {
                $assignment = app(AssignRoleToUserAction::class)->handle($user, $superRole);
                app(AssignScopeToRoleAssignmentAction::class)->handle($assignment, $scope);
                $assigned++;
            }
        } else {
            ($output ?? $this->output)->writeln('<comment>Super administrator role missing — falling back to all bundle roles.</comment>');
            foreach (array_keys(AuthorizationBundleCatalog::BUNDLES) as $code) {
                $role = Role::query()->where('code', $code)->first();
                if ($role === null) {
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

                $assignment = app(AssignRoleToUserAction::class)->handle($user, $role);
                app(AssignScopeToRoleAssignmentAction::class)->handle($assignment, $scope);
                $assigned++;
            }
        }

        ($output ?? $this->output)->writeln(sprintf(
            '<info>Granted super-admin access to %s (%s): %d role assignment(s) added, %d already present.</info>',
            $user->name ?? $email,
            $email,
            $assigned,
            $skipped,
        ));

        return self::SUCCESS;
    }
}
