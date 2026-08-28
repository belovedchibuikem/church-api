<?php

namespace App\Privacy;

use App\Models\DataSubjectRequest;
use App\Models\User;
use App\Privacy\Contracts\DataSubjectRequestExecutionPolicy;

final class ExportTypeDataSubjectRequestExecutionPolicy implements DataSubjectRequestExecutionPolicy
{
    public function decide(DataSubjectRequest $request, User $actor): ExecutionDecision
    {
        if ($request->request_type === DataSubjectRequestType::Export) {
            return ExecutionDecision::allowed();
        }

        return ExecutionDecision::denied();
    }
}
