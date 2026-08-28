<?php

namespace App\Exceptions;

use DomainException;

class KcaMentorAssignmentException extends DomainException
{
    public function __construct()
    {
        parent::__construct('The reviewer is not the enrollment current assigned mentor.');
    }
}
