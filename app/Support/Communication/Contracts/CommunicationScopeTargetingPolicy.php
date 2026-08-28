<?php

namespace App\Support\Communication\Contracts;

use App\Models\User;
use App\Support\Authorization\ScopeReference;
use App\Support\Communication\DatabaseCommunicationScopeTargetingPolicy;
use Illuminate\Container\Attributes\Bind;

#[Bind(DatabaseCommunicationScopeTargetingPolicy::class)]
interface CommunicationScopeTargetingPolicy
{
    public function allows(User $user, ScopeReference $audienceScope): bool;
}
