<?php

namespace App\Exceptions;

use RuntimeException;

class FileAssetUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The file asset is not available for reading.');
    }
}
