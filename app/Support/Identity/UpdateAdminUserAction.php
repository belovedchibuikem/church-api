<?php

namespace App\Support\Identity;

use App\Models\User;
use App\Queries\Admin\ListScopedUsersQuery;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use App\Support\Authorization\ScopeReference;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateAdminUserAction
{
    public function __construct(
        private ListScopedUsersQuery $users,
        private UpdatePersonProfileAction $updateProfile,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    /**
     * @param  array{name?: string, email?: string, profile?: array{given_name: string, family_name: string, middle_name?: string|null, preferred_name?: string|null, phone?: string|null, country?: string|null, region?: string|null, locality?: string|null}}  $attributes
     */
    public function handle(User $actor, ScopeReference $scope, string $userPublicId, array $attributes): User
    {
        $target = $this->users->findOrFail($actor, $scope, $userPublicId);

        return DB::transaction(function () use ($actor, $scope, $target, $attributes): User {
            $changedFields = [];
            $name = isset($attributes['name']) ? trim((string) $attributes['name']) : null;
            if ($name === '') {
                throw new InvalidArgumentException('User name must not be empty.');
            }

            if ($name !== null && $name !== $target->name) {
                $target->name = $name;
                $changedFields[] = 'name';
            }

            if (isset($attributes['email'])) {
                $email = strtolower(trim((string) $attributes['email']));
                if ($email === '') {
                    throw new InvalidArgumentException('User email must not be empty.');
                }
                if ($email !== $target->email) {
                    $target->email = $email;
                    $target->email_verified_at = null;
                    $changedFields[] = 'email';
                }
            }

            if ($changedFields !== []) {
                $target->save();
                $this->recordAuditEvent->handle(new AuditEventData(
                    action: 'identity.user.updated',
                    actor: $actor,
                    targetType: 'user',
                    targetId: $target->public_id,
                    metadata: ['changed_fields' => $changedFields],
                ));
            }

            if (isset($attributes['profile']) && $target->person !== null) {
                $this->updateProfile->handle($target->person, $attributes['profile'], $actor);
            }

            return $this->users->findOrFail($actor, $scope, $target->public_id);
        }, attempts: 3);
    }
}
