<?php

namespace App\Exceptions;

use DomainException;

class MissionSoulAlreadyLinkedException extends DomainException
{
    public const ERROR_CODE = 'MISSION_SOUL_ALREADY_LINKED';
}
