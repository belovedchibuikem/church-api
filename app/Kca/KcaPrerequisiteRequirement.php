<?php

namespace App\Kca;

enum KcaPrerequisiteRequirement: string
{
    case PreviousModuleComplete = 'previous_module_complete';
    case AssignmentSubmitted = 'assignment_submitted';
    case MentorApproval = 'mentor_approval';
    case QuizPassed = 'quiz_passed';
    case SpiritualExerciseComplete = 'spiritual_exercise_complete';
}
