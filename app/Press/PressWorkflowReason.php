<?php

namespace App\Press;

use InvalidArgumentException;

final class PressWorkflowReason
{
    public static function validate(string $reasonCode): string
    {
        $reasonCode = trim($reasonCode);

        if (
            strlen($reasonCode) > 100
            || preg_match('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $reasonCode) !== 1
        ) {
            throw new InvalidArgumentException('The workflow reason must be a stable lowercase code.');
        }

        return $reasonCode;
    }
}
