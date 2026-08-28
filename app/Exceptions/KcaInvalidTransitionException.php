<?php

namespace App\Exceptions;

use DomainException;

class KcaInvalidTransitionException extends DomainException
{
    public function __construct(string $workflow, string $from, string $to)
    {
        parent::__construct("Invalid {$workflow} transition from {$from} to {$to}.");
    }
}
