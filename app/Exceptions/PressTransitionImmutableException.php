<?php

namespace App\Exceptions;

use RuntimeException;

class PressTransitionImmutableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Press workflow transition records are append-only.');
    }
}
