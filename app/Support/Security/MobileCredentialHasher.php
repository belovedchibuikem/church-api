<?php

namespace App\Support\Security;

class MobileCredentialHasher
{
    public function hash(string $credential): string
    {
        return hash_hmac('sha256', $credential, (string) config('app.key'));
    }
}
