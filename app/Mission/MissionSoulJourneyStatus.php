<?php

namespace App\Mission;

enum MissionSoulJourneyStatus: string
{
    case New = 'new';
    case MentorAssigned = 'mentor_assigned';
    case FollowUpActive = 'follow_up_active';
    case FollowUpCompleted = 'follow_up_completed';
    case Closed = 'closed';

    public function acceptsFollowUp(): bool
    {
        return in_array($this, [self::MentorAssigned, self::FollowUpActive], true);
    }
}
