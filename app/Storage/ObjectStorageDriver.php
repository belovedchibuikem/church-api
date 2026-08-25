<?php

namespace App\Storage;

enum ObjectStorageDriver: string
{
    case S3 = 's3';
}
