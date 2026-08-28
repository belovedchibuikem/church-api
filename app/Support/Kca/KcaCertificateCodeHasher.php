<?php

namespace App\Support\Kca;

use InvalidArgumentException;

class KcaCertificateCodeHasher
{
    public function hash(string $verificationCode): string
    {
        $key = config('app.key');

        if (! is_string($key) || $key === '') {
            throw new InvalidArgumentException('The application key is required for certificate protection.');
        }

        return hash_hmac('sha256', $verificationCode, $key);
    }
}
