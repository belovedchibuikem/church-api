<?php

namespace App\Administration;

enum AdminWorkItemPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';
}
