<?php

namespace App\Exceptions;

use DomainException;

class KcaCertificateImmutableException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Issued KCA certificates are immutable.');
    }
}
