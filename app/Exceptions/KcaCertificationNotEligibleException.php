<?php

namespace App\Exceptions;

use App\Support\Kca\KcaCertificationEligibilityDecision;
use DomainException;

class KcaCertificationNotEligibleException extends DomainException
{
    public function __construct(public readonly KcaCertificationEligibilityDecision $decision)
    {
        parent::__construct('KCA certification eligibility was denied.');
    }
}
