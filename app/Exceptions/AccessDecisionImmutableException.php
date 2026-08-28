<?php

namespace App\Exceptions;

use Exception;

class AccessDecisionImmutableException extends Exception
{
    public function __construct()
    {
        parent::__construct('Access decisions are append-only records.');
    }
}
