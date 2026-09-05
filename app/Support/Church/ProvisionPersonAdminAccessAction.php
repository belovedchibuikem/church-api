<?php

namespace App\Support\Church;

use App\Models\Church;
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
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProvisionPersonAdminAccessAction
{
    public function __construct(
        private AssignRoleToUserAction $assignRole,
        private AssignScopeToRoleAssignmentAction $assignScope,
        private LinkUserToPersonAction $linkUser,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        Person $person,
        Church $church,
        ?User $actor = null,
        ?string $email = null,
    ): User {
        return DB::transaction(function () use ($person, $church, $actor, $email): User {
            $lockedPerson = Person::query()
                ->with(['profile', 'user'])
                ->lockForUpdate()
                ->findOrFail($person->getKey());
            $lockedChurch = Church::query()->lockForUpdate()->findOrFail($church->getKey());
            $user = $lockedPerson->user;

            if ($user === null) {
                $resolvedEmail = $this->resolveEmail($email);
                $normalizedEmail = mb_strtolower(trim($resolvedEmail));
                if (User::query()->where('email', $normalizedEmail)->exists()) {
                    throw new InvalidArgumentException('A user with this email already exists.');
                }
                try {
                    $user = User::query()->create([
                        'name' => PersonDisplayName::of($lockedPerson),
                        'email' => $normalizedEmail,
                        'password' => Str::password(20),
                    ]);
                } catch (QueryException $exception) {
                    if (($exception->errorInfo[1] ?? null) === 1062) {
                        throw new InvalidArgumentException('A user with this email already exists.');
                    }

                    throw $exception;
                }
                $user = $this->linkUser->handle($user, $lockedPerson, $actor);
                Password::sendResetLink(['email' => $user->email]);
            }

            $role = Role::query()
                ->where('code', AuthorizationBundleCatalog::CHURCH_OPERATIONS_ADMINISTRATOR_ROLE)
                ->firstOrFail();
            $assignment = $this->assignRole->handle($user, $role, $actor);
            $this->assignScope->handle(
                $assignment,
                new ScopeReference('church', $lockedChurch->public_id),
                $actor,
            );

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'church.leadership.admin_access_granted',
                actor: $actor,
                targetType: 'user',
                targetId: $user->public_id,
                scopeType: 'church',
                scopeId: $lockedChurch->public_id,
                metadata: ['person_id' => $lockedPerson->public_id],
            ));

            return $user->fresh(['person.profile']);
        }, attempts: 3);
    }

    private function resolveEmail(?string $email): string
    {
        $resolved = trim((string) $email);
        if ($resolved === '' || ! filter_var($resolved, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid email is required to grant church admin access.');
        }

        return $resolved;
    }
}
