<?php

namespace App\Safeguarding;

enum IncidentStatus: string
{
    case Reported = 'reported';
    case Triaged = 'triaged';
    case UnderReview = 'under_review';
    case Referred = 'referred';
    case Closed = 'closed';
}
