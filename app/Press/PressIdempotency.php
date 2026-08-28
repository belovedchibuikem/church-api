<?php

namespace App\Press;

use InvalidArgumentException;
use JsonException;

final class PressIdempotency
{
    public static function keyHash(string $idempotencyKey): string
    {
        $idempotencyKey = trim($idempotencyKey);

        if (strlen($idempotencyKey) < 16 || strlen($idempotencyKey) > 200) {
            throw new InvalidArgumentException('The idempotency key must contain between 16 and 200 characters.');
        }

        return hash_hmac('sha256', $idempotencyKey, (string) config('app.key'));
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws JsonException
     */
    public static function fingerprint(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
