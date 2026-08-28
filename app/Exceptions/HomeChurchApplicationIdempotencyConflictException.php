<?php

namespace App\Exceptions;

use RuntimeException;

class HomeChurchApplicationIdempotencyConflictException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The idempotency key has already been used with a different Home Church application payload.');
    }
}
