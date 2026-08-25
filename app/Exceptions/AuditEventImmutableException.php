<?php

namespace App\Exceptions;

use LogicException;

class AuditEventImmutableException extends LogicException
{
    public function __construct()
    {
        parent::__construct('Audit events are append-only and cannot be changed or deleted.');
    }
}
