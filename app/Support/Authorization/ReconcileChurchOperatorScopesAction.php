<?php

namespace App\Support\Authorization;

use App\Church\ChurchMembershipStatus;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\ChurchRoleAssignment;
use App\Models\RoleAssignment;
use App\Models\User;

class ReconcileChurchOperatorScopesAction
{
    public function __construct(
        private AssignScopeToRoleAssignmentAction $assignScope,
    ) {}

    /**
     * Church operations roles are unusable without a church scope. Attach churches
     * from leadership appointments, then active memberships, when none are present.
     *
     * @return list<string> Church public ids attached during this call
     */
    public function handle(User $user): array
    {
        $user->loadMissing('person');
        if ($user->person_id === null) {
            return [];
        }

        $assignments = RoleAssignment::query()
            ->with(['role:id,code', 'scopeAssignments:id,role_assignment_id,scope_type,scope_key'])
            ->whereBelongsTo($user)
            ->active()
            ->whereHas('role', fn ($query) => $query->where('code', AuthorizationBundleCatalog::CHURCH_OPERATIONS_ADMINISTRATOR_ROLE))
            ->get();

        if ($assignments->isEmpty()) {
            return [];
        }

        $churchPublicIds = $this->inferredChurchPublicIds((int) $user->person_id);
        if ($churchPublicIds === []) {
            return [];
        }

        $attached = [];
        foreach ($assignments as $assignment) {
            $alreadyHasChurchScope = $assignment->scopeAssignments
                ->contains(fn ($scope): bool => $scope->scope_type === 'church');
            if ($alreadyHasChurchScope) {
                continue;
            }

            foreach ($churchPublicIds as $churchPublicId) {
                $this->assignScope->handle(
                    $assignment,
                    new ScopeReference('church', $churchPublicId),
                );
                $attached[] = $churchPublicId;
            }
        }

        return array_values(array_unique($attached));
    }

    /**
     * @return list<string>
     */
    private function inferredChurchPublicIds(int $personId): array
    {
        $leadershipChurchIds = ChurchRoleAssignment::query()
            ->where('person_id', $personId)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->pluck('church_id');

        $membershipChurchIds = ChurchMembership::query()
            ->where('person_id', $personId)
            ->where('status', ChurchMembershipStatus::Active)
            ->pluck('church_id');

        $internalIds = $leadershipChurchIds->isNotEmpty()
            ? $leadershipChurchIds
            : $membershipChurchIds;

        if ($internalIds->isEmpty()) {
            return [];
        }

        return Church::query()
            ->whereIn('id', $internalIds->unique()->all())
            ->pluck('public_id')
            ->filter()
            ->values()
            ->all();
    }
}
