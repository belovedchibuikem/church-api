<?php

namespace App\Storage\Contracts;

use App\Models\ObjectStorageConfiguration;
use App\Storage\Data\ObjectStorageValidationResult;

interface ObjectStorageConnectionValidator
{
    public function validate(ObjectStorageConfiguration $configuration): ObjectStorageValidationResult;
}
