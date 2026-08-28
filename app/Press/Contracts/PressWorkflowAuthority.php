<?php

namespace App\Press\Contracts;

use App\Models\PressPublication;
use App\Models\User;
use App\Press\PressWorkflowOperation;

interface PressWorkflowAuthority
{
    public function allows(User $actor, PressWorkflowOperation $operation, PressPublication $publication): bool;
}
