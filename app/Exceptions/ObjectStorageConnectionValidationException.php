<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

class ObjectStorageConnectionValidationException extends Exception implements ShouldntReport
{
    public function __construct(public readonly string $failureCode)
    {
        parent::__construct('The object storage connection could not be activated.');
    }
}
