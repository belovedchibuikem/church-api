<?php

namespace App\Reporting;

use App\Models\AlertOccurrence;
use App\Models\User;
use App\Reporting\Contracts\AlertVisibilityPolicy;

class DenyAlertVisibilityPolicy implements AlertVisibilityPolicy
{
    public function allows(User $user, AlertOccurrence $occurrence): bool
    {
        return false;
    }
}
