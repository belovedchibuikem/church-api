<?php

namespace App\Reporting\Contracts;

use App\Models\AlertOccurrence;
use App\Models\User;
use App\Reporting\AuthenticatedAlertVisibilityPolicy;
use Illuminate\Container\Attributes\Bind;

#[Bind(AuthenticatedAlertVisibilityPolicy::class)]
interface AlertVisibilityPolicy
{
    public function allows(User $user, AlertOccurrence $occurrence): bool;
}
