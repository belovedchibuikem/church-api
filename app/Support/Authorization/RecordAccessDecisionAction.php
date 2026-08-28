<?php

namespace App\Support\Authorization;

use App\Models\AccessDecision;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RecordAccessDecisionAction
{
    public function handle(
        User $actor,
        AuthorizationCode $permission,
        ScopeReference $scope,
        AccessDecisionReason $reason,
        ?RoleAssignment $matchedRoleAssignment = null,
    ): AccessDecision {
        $allowed = $reason === AccessDecisionReason::Allowed;

        if ($allowed !== ($matchedRoleAssignment !== null)) {
            throw new InvalidArgumentException('Allowed decisions require exactly one matched role assignment.');
        }

        $correlationId = Context::get('correlation_id');

        return AccessDecision::query()->create([
            'actor_user_id' => $actor->getKey(),
            'matched_role_assignment_id' => $matchedRoleAssignment?->getKey(),
            'permission_code' => $permission->value,
            'scope_type' => $scope->type,
            'scope_key' => $scope->key,
            'allowed' => $allowed,
            'reason_code' => $reason,
            'correlation_id' => is_string($correlationId) && Str::isUuid($correlationId)
                ? $correlationId
                : null,
            'decided_at' => now()->utc(),
        ]);
    }
}
