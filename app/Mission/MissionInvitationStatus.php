<?php

namespace App\Mission;

enum MissionInvitationStatus: string
{
    case Received = 'received';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Planning = 'planning';
    case Confirmed = 'confirmed';
    case Completed = 'completed';

    public function next(): ?self
    {
        return match ($this) {
            self::Received => self::UnderReview,
            self::UnderReview => self::Approved,
            self::Approved => self::Planning,
            self::Planning => self::Confirmed,
            self::Confirmed => self::Completed,
            self::Completed => null,
        };
    }
}
