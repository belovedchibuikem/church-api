<?php

namespace App\Support\Kca;

use App\Kca\KcaAssignmentState;
use App\Models\KcaEnrollment;
use App\Support\Kca\Contracts\KcaCertificationEligibilityPolicy;

class EnrollmentCompletionKcaCertificationEligibilityPolicy implements KcaCertificationEligibilityPolicy
{
    public function decide(KcaEnrollment $enrollment): KcaCertificationEligibilityDecision
    {
        $assignments = $enrollment->assignments()->get();

        if ($assignments->isEmpty() || $assignments->contains(
            fn ($assignment): bool => $assignment->state !== KcaAssignmentState::FinalAssessment,
        )) {
            return new KcaCertificationEligibilityDecision(
                eligible: false,
                reasonCode: 'assignments_incomplete',
                unmetRequirements: ['final_assessment'],
            );
        }

        return KcaCertificationEligibilityDecision::approved();
    }
}
