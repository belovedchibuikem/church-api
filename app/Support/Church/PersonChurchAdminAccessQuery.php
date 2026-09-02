<?php

namespace App\Support\Church;

use App\Models\Church;
use App\Models\Person;
use App\Models\RoleAssignment;
use App\Support\Authorization\AuthorizationBundleCatalog;

class PersonChurchAdminAccessQuery
{
    public function handle(Person $person, Church $church): bool
    {
        $user = $person->user;
        if ($user === null) {
            return false;
        }

        return RoleAssignment::query()
            ->whereBelongsTo($user)
            ->active()
            ->whereHas('role', fn ($query) => $query->where(
                'code',
                AuthorizationBundleCatalog::CHURCH_OPERATIONS_ADMINISTRATOR_ROLE,
            ))
            ->whereHas('scopeAssignments', fn ($query) => $query
                ->where('scope_type', 'church')
                ->where('scope_key', $church->public_id))
            ->exists();
    }
}
