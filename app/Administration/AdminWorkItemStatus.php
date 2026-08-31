<?php

namespace App\Administration;

enum AdminWorkItemStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Archived = 'archived';
}
