<?php

namespace App\Safeguarding;

use App\Models\User;
use App\Safeguarding\Contracts\RestrictedRecordAccessPolicy;

final class PendingRestrictedRecordAccessPolicy implements RestrictedRecordAccessPolicy
{
    public function decide(User $actor, string $recordType, string $recordId, string $operation): AccessDecision
    {
        return AccessDecision::denied();
    }
}
