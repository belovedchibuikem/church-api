<?php

namespace App\Privacy;

enum DataSubjectRequestType: string
{
    case Export = 'export';
    case Correction = 'correction';
    case Deletion = 'deletion';
    case Restriction = 'restriction';
}
