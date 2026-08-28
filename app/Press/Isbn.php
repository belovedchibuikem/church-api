<?php

namespace App\Press;

use InvalidArgumentException;

final readonly class Isbn
{
    private function __construct(
        public string $value,
        public PressIsbnType $type,
    ) {}

    public static function from(string $value): self
    {
        $normalized = strtoupper(preg_replace('/[\s-]+/', '', trim($value)) ?? '');

        if (preg_match('/\A\d{9}[\dX]\z/', $normalized) === 1 && self::isValidIsbn10($normalized)) {
            return new self($normalized, PressIsbnType::Isbn10);
        }

        if (preg_match('/\A\d{13}\z/', $normalized) === 1 && self::isValidIsbn13($normalized)) {
            return new self($normalized, PressIsbnType::Isbn13);
        }

        throw new InvalidArgumentException('The ISBN checksum is invalid.');
    }

    private static function isValidIsbn10(string $isbn): bool
    {
        $sum = 0;

        for ($index = 0; $index < 10; $index++) {
            $digit = $isbn[$index] === 'X' ? 10 : (int) $isbn[$index];
            $sum += (10 - $index) * $digit;
        }

        return $sum % 11 === 0;
    }

    private static function isValidIsbn13(string $isbn): bool
    {
        $sum = 0;

        for ($index = 0; $index < 13; $index++) {
            $sum += (int) $isbn[$index] * ($index % 2 === 0 ? 1 : 3);
        }

        return $sum % 10 === 0;
    }
}
