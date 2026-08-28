<?php

namespace App\Support\Authorization;

enum AccessDecisionReason: string
{
    case Allowed = 'allowed';
    case AccountSuspended = 'account_suspended';
    case PermissionNotAssigned = 'permission_not_assigned';
    case ScopeNotAssigned = 'scope_not_assigned';
    case ScopeNotContained = 'scope_not_contained';
}
