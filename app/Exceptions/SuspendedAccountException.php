<?php

namespace App\Exceptions;

use RuntimeException;

class SuspendedAccountException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Suspended accounts cannot create new security credentials or sessions.');
    }
}
