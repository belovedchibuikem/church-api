<?php

namespace App\Support\Kca;

use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\AuthorizationBundleCatalog;
use App\Support\Authorization\ScopeReference;
use App\Support\Identity\LinkUserToPersonAction;
use App\Support\Identity\PersonDisplayName;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

class ProvisionKcaStudentLoginAction
{
    public function __construct(
        private LinkUserToPersonAction $linkUser,
        private AssignRoleToUserAction $assignRole,
        private AssignScopeToRoleAssignmentAction $assignScope,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        Person $person,
        string $email,
        #[\SensitiveParameter] string $password,
        User $actor,
    ): User {
        $normalizedEmail = mb_strtolower(trim($email));
        if ($normalizedEmail === '' || ! filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid login email is required.');
        }

        return DB::transaction(function () use ($person, $normalizedEmail, $password, $actor): User {
            $lockedPerson = Person::query()
                ->with(['profile', 'user'])
                ->lockForUpdate()
                ->findOrFail($person->getKey());

            if ($lockedPerson->user !== null) {
                throw ValidationException::withMessages([
                    'create_login' => ['This person already has a login account.'],
                ]);
            }

            try {
                $user = User::query()->create([
                    'name' => PersonDisplayName::of($lockedPerson),
                    'email' => $normalizedEmail,
                    'password' => $password,
                    'email_verified_at' => now()->utc(),
                ]);
            } catch (QueryException $exception) {
                if (($exception->errorInfo[1] ?? null) !== 1062) {
                    throw $exception;
                }

                throw ValidationException::withMessages([
                    'email' => ['This login email is already in use.'],
                ]);
            }

            $user = $this->linkUser->handle($user, $lockedPerson, $actor);
            $this->grantMemberAccess($user, $actor);

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.student.login_provisioned',
                actor: $actor,
                targetType: 'user',
                targetId: $user->public_id,
                scopeType: 'global',
                scopeId: 'platform',
                metadata: ['person_id' => $lockedPerson->public_id],
            ));

            return $user->fresh(['person.profile']);
        }, attempts: 3);
    }

    private function grantMemberAccess(User $user, User $actor): void
    {
        $role = Role::query()
            ->where('code', AuthorizationBundleCatalog::MEMBER_SECURITY_ROLE)
            ->first();

        if ($role === null) {
            throw new RuntimeException(
                'Member security role is not provisioned. Run authorization seeders before KCA student login provisioning.',
            );
        }

        $assignment = $this->assignRole->handle($user, $role, $actor);
        $this->assignScope->handle($assignment, new ScopeReference('global', 'platform'), $actor);
    }
}
