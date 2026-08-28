<?php

namespace App\Files;

enum FileAssetClassification: string
{
    case Public = 'public';
    case Internal = 'internal';
    case Confidential = 'confidential';
    case Restricted = 'restricted';
}
