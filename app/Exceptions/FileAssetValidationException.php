<?php

namespace App\Exceptions;

use RuntimeException;

class FileAssetValidationException extends RuntimeException
{
    public function __construct(public readonly string $reasonCode)
    {
        parent::__construct('The file asset did not satisfy the configured content policy.');
    }
}
