<?php

namespace App\Safeguarding\Contracts;

use App\Models\User;
use App\Safeguarding\AccessDecision;

interface RestrictedRecordAccessPolicy
{
    public function decide(User $actor, string $recordType, string $recordId, string $operation): AccessDecision;
}
