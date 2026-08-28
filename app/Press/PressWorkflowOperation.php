<?php

namespace App\Press;

enum PressWorkflowOperation: string
{
    case CreatePublication = 'create_publication';
    case ManageContributors = 'manage_contributors';
    case TransitionPublication = 'transition_publication';
    case AssignIsbn = 'assign_isbn';
    case CreateMachineTranslation = 'create_machine_translation';
    case ReviewTranslation = 'review_translation';
    case ApproveTranslation = 'approve_translation';
}
