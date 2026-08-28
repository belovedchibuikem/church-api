<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function suspend(User $actor, User $target): bool
    {
        return ! $actor->is($target);
    }

    public function reactivate(User $actor, User $target): bool
    {
        return ! $actor->is($target);
    }
}
