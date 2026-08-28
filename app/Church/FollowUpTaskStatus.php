<?php

namespace App\Church;

enum FollowUpTaskStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
