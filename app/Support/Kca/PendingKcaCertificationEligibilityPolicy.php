<?php

namespace App\Support\Kca;

use App\Models\KcaEnrollment;
use App\Support\Kca\Contracts\KcaCertificationEligibilityPolicy;

class PendingKcaCertificationEligibilityPolicy implements KcaCertificationEligibilityPolicy
{
    public function decide(KcaEnrollment $enrollment): KcaCertificationEligibilityDecision
    {
        return KcaCertificationEligibilityDecision::policyPending();
    }
}
