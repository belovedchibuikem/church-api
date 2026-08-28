<?php

namespace App\Safeguarding;

enum GuardianRelationshipStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Ended = 'ended';
}
