<?php

namespace App\Press;

enum PressContributorRole: string
{
    case Author = 'author';
    case Speaker = 'speaker';
    case Editor = 'editor';
    case Translator = 'translator';
    case Reviewer = 'reviewer';
    case Designer = 'designer';
    case Contributor = 'contributor';
}
