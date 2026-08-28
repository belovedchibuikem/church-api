<?php

namespace App\Support\Kca;

use App\Exceptions\KcaInvalidTransitionException;
use App\Kca\KcaAssignmentState;

class KcaAssignmentTransitionService
{
    public function assertCanTransition(KcaAssignmentState $from, KcaAssignmentState $to): void
    {
        $allowed = match ($from) {
            KcaAssignmentState::Draft => [KcaAssignmentState::Assigned],
            KcaAssignmentState::Assigned => [KcaAssignmentState::Submitted],
            KcaAssignmentState::Submitted => [KcaAssignmentState::MentorReview],
            KcaAssignmentState::MentorReview => [
                KcaAssignmentState::Resubmit,
                KcaAssignmentState::Approved,
                KcaAssignmentState::NeedsAttention,
            ],
            KcaAssignmentState::Resubmit => [KcaAssignmentState::Submitted],
            KcaAssignmentState::Approved,
            KcaAssignmentState::NeedsAttention => [KcaAssignmentState::AdminReview],
            KcaAssignmentState::AdminReview => [KcaAssignmentState::FinalAssessment],
            KcaAssignmentState::FinalAssessment => [],
        };

        if (! in_array($to, $allowed, true)) {
            throw new KcaInvalidTransitionException('kca_assignment', $from->value, $to->value);
        }
    }
}
