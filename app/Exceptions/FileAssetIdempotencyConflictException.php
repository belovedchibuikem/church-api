<?php

namespace App\Exceptions;

use RuntimeException;

class FileAssetIdempotencyConflictException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The idempotency key was already used for different file content.');
    }
}
