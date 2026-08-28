<?php

namespace App\Privacy;

use App\Models\DataSubjectRequest;
use App\Models\User;
use App\Privacy\Contracts\DataSubjectRequestExecutionPolicy;

final class PendingDataSubjectRequestExecutionPolicy implements DataSubjectRequestExecutionPolicy
{
    public function decide(DataSubjectRequest $request, User $actor): ExecutionDecision
    {
        return ExecutionDecision::denied();
    }
}
