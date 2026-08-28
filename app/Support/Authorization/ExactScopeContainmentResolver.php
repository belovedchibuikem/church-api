<?php

namespace App\Support\Authorization;

use App\Models\User;
use App\Support\Authorization\Contracts\ScopeContainmentResolver;

class ExactScopeContainmentResolver implements ScopeContainmentResolver
{
    public function contains(
        ScopeReference $assignedScope,
        ScopeReference $requestedScope,
        User $actor,
    ): bool {
        return $assignedScope->type === $requestedScope->type
            && $assignedScope->key === $requestedScope->key;
    }
}
