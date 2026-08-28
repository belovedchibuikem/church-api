<?php

namespace App\Files;

enum FileAssetStatus: string
{
    case Quarantined = 'quarantined';
    case Pending = 'pending';
    case Available = 'available';
    case Rejected = 'rejected';
    case Deleted = 'deleted';
}
