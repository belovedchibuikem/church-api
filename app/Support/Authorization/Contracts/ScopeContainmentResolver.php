<?php

namespace App\Support\Authorization\Contracts;

use App\Models\User;
use App\Support\Authorization\ScopeReference;

interface ScopeContainmentResolver
{
    public function contains(
        ScopeReference $assignedScope,
        ScopeReference $requestedScope,
        User $actor,
    ): bool;
}
