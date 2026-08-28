<?php

namespace App\Kca;

enum KcaApplicationState: string
{
    case Received = 'received';
    case Reviewed = 'reviewed';
    case Accepted = 'accepted';
    case ProvisionallyAccepted = 'provisionally_accepted';
    case Deferred = 'deferred';
    case NotAccepted = 'not_accepted';

    public function isAdmissionOutcome(): bool
    {
        return match ($this) {
            self::Accepted,
            self::ProvisionallyAccepted,
            self::Deferred,
            self::NotAccepted => true,
            self::Received,
            self::Reviewed => false,
        };
    }

    public function permitsEnrollment(): bool
    {
        return $this === self::Accepted || $this === self::ProvisionallyAccepted;
    }
}
