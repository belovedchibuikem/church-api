<?php

namespace App\Communication;

enum CommunicationKind: string
{
    case Notification = 'notification';
    case Announcement = 'announcement';
    case Broadcast = 'broadcast';
    case Newsletter = 'newsletter';
}
