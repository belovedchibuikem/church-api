<?php

namespace App\Storage\Contracts;

use Illuminate\Contracts\Filesystem\Filesystem;

interface ObjectStorageDiskResolver
{
    public function disk(): Filesystem;
}
