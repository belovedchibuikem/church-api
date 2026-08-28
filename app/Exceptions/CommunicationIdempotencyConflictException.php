<?php

namespace App\Exceptions;

use DomainException;

class CommunicationIdempotencyConflictException extends DomainException
{
    public function __construct()
    {
        parent::__construct('The communication idempotency key was already used for different data.');
    }
}
