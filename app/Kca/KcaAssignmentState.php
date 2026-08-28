<?php

namespace App\Kca;

enum KcaAssignmentState: string
{
    case Draft = 'draft';
    case Assigned = 'assigned';
    case Submitted = 'submitted';
    case MentorReview = 'mentor_review';
    case Resubmit = 'resubmit';
    case Approved = 'approved';
    case NeedsAttention = 'needs_attention';
    case AdminReview = 'admin_review';
    case FinalAssessment = 'final_assessment';
}
