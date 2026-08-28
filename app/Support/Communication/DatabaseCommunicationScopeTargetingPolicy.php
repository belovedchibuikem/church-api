<?php

namespace App\Support\Communication;

use App\Models\RoleAssignment;
use App\Models\User;
use App\Support\Authorization\Contracts\ScopeContainmentResolver;
use App\Support\Authorization\ScopeReference;
use App\Support\Communication\Contracts\CommunicationScopeTargetingPolicy;

class DatabaseCommunicationScopeTargetingPolicy implements CommunicationScopeTargetingPolicy
{
    public function __construct(private ScopeContainmentResolver $scopeContainment) {}

    public function allows(User $user, ScopeReference $audienceScope): bool
    {
        $assignments = RoleAssignment::query()
            ->select(['id', 'user_id'])
            ->whereBelongsTo($user)
            ->active()
            ->with('scopeAssignments:id,role_assignment_id,scope_type,scope_key')
            ->get();

        foreach ($assignments as $assignment) {
            foreach ($assignment->scopeAssignments as $scopeAssignment) {
                $assignedScope = ScopeReference::fromAssignment($scopeAssignment);

                if ($this->scopeContainment->contains($audienceScope, $assignedScope, $user)) {
                    return true;
                }
            }
        }

        return false;
    }
}
