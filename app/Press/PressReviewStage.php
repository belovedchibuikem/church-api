<?php

namespace App\Press;

enum PressReviewStage: string
{
    case Editorial = 'editorial';
    case Theological = 'theological';
    case Copy = 'copy';
    case Design = 'design';
    case Translation = 'translation';
}
