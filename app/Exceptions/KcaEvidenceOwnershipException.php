<?php

namespace App\Exceptions;

use DomainException;

class KcaEvidenceOwnershipException extends DomainException
{
    public function __construct()
    {
        parent::__construct('KCA evidence must belong to the assignment enrollment and canonical person.');
    }
}
