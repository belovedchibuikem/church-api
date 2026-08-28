<?php

namespace App\Exceptions;

use RuntimeException;

class RevokedDeviceException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The revoked device cannot be registered or used for a new session.');
    }
}
