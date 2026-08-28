<?php

namespace App\Exceptions;

use DomainException;

class KcaIdempotencyConflictException extends DomainException
{
    public function __construct()
    {
        parent::__construct('The KCA idempotency key was already used for different data.');
    }
}
