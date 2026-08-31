<?php

namespace App\Press;

enum PressReviewDecision: string
{
    case Approved = 'approved';
    case ChangesRequested = 'changes_requested';
    case InformationRequired = 'information_required';
    case Rejected = 'rejected';
}
