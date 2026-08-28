<?php

namespace App\Privacy\Contracts;

use App\Models\DataSubjectRequest;
use App\Models\User;
use App\Privacy\ExecutionDecision;

interface DataSubjectRequestExecutionPolicy
{
    public function decide(DataSubjectRequest $request, User $actor): ExecutionDecision;
}
