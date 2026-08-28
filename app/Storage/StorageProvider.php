<?php

namespace App\Storage;

enum StorageProvider: string
{
    case Local = 'local';
    case S3 = 's3';
}
