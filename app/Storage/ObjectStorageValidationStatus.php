<?php

namespace App\Storage;

enum ObjectStorageValidationStatus: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
