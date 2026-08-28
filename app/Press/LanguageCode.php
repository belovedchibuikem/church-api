<?php

namespace App\Press;

use InvalidArgumentException;

final class LanguageCode
{
    public static function normalize(string $languageCode): string
    {
        $parts = explode('-', trim(str_replace('_', '-', $languageCode)));

        if (! preg_match('/\A[a-zA-Z]{2,3}(?:-[a-zA-Z0-9]{2,8})*\z/', implode('-', $parts))) {
            throw new InvalidArgumentException('The language code must be a valid BCP 47-style code.');
        }

        $normalized = [strtolower(array_shift($parts))];

        foreach ($parts as $part) {
            $normalized[] = match (strlen($part)) {
                2 => strtoupper($part),
                4 => ucfirst(strtolower($part)),
                default => strtolower($part),
            };
        }

        return implode('-', $normalized);
    }
}
