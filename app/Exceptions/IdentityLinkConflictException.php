<?php

namespace App\Exceptions;

use LogicException;

class IdentityLinkConflictException extends LogicException
{
    public function __construct()
    {
        parent::__construct('The user or person is already linked to another canonical identity.');
    }
}
