<?php

namespace App\Exceptions;

use RuntimeException;

class DeviceOwnershipException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The device does not belong to the session owner.');
    }
}
