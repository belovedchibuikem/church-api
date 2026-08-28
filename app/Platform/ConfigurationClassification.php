<?php

namespace App\Platform;

enum ConfigurationClassification: string
{
    case Internal = 'internal';
    case Confidential = 'confidential';
}
