<?php

namespace App\Support\Kca\Contracts;

use App\Models\KcaEnrollment;
use App\Support\Kca\KcaCertificationEligibilityDecision;

interface KcaCertificationEligibilityPolicy
{
    public function decide(KcaEnrollment $enrollment): KcaCertificationEligibilityDecision;
}
