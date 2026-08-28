<?php

namespace App\Church;

enum HomeChurchApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case InterviewOrientation = 'interview_orientation';
    case Approved = 'approved';
    case Active = 'active';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    case Closed = 'closed';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => in_array($target, [self::Submitted, self::Closed], true),
            self::Submitted => in_array($target, [self::UnderReview, self::Rejected, self::Closed], true),
            self::UnderReview => in_array($target, [self::InterviewOrientation, self::Rejected, self::Closed], true),
            self::InterviewOrientation => in_array($target, [self::Approved, self::Rejected, self::Closed], true),
            self::Approved => in_array($target, [self::Active, self::Closed], true),
            self::Active => in_array($target, [self::Suspended, self::Closed], true),
            self::Suspended => in_array($target, [self::Active, self::Closed], true),
            self::Rejected, self::Closed => false,
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Rejected, self::Closed], true);
    }
}
