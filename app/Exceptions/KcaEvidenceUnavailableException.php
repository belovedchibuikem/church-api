<?php

namespace App\Exceptions;

use DomainException;

class KcaEvidenceUnavailableException extends DomainException
{
    public function __construct()
    {
        parent::__construct('KCA evidence is not available for review.');
    }
}
