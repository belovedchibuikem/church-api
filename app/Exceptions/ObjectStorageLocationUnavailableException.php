<?php

namespace App\Exceptions;

use RuntimeException;

class ObjectStorageLocationUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The recorded storage location is unavailable.');
    }
}
