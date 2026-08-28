<?php

namespace App\Support\Security;

use Illuminate\Support\Str;
use InvalidArgumentException;

class TotpService
{
    private const string Alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(): string
    {
        return $this->encodeBase32(random_bytes(20));
    }

    public function provisioningUri(string $secret, string $accountName): string
    {
        $issuer = (string) config('app.name');
        $label = rawurlencode($issuer.':'.$accountName);

        return "otpauth://totp/{$label}?secret={$secret}&issuer=".rawurlencode($issuer).'&algorithm=SHA1&digits=6&period=30';
    }

    public function matchingCounter(
        string $secret,
        string $code,
        ?int $timestamp = null,
        int $window = 1,
    ): ?int {
        if (! Str::isMatch('/\A[0-9]{6}\z/', $code)) {
            return null;
        }

        $counter = intdiv($timestamp ?? time(), 30);

        for ($offset = -$window; $offset <= $window; $offset++) {
            $candidateCounter = $counter + $offset;

            if ($candidateCounter >= 0 && hash_equals($this->codeAtCounter($secret, $candidateCounter), $code)) {
                return $candidateCounter;
            }
        }

        return null;
    }

    public function codeAtCounter(string $secret, int $counter): string
    {
        $decodedSecret = $this->decodeBase32($secret);
        $counterBytes = pack('N2', intdiv($counter, 4294967296), $counter % 4294967296);
        $hash = hash_hmac('sha1', $counterBytes, $decodedSecret, true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($binary % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    private function encodeBase32(string $bytes): string
    {
        $bits = '';

        foreach (unpack('C*', $bytes) as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';

        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::Alphabet[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $encoded;
    }

    private function decodeBase32(string $secret): string
    {
        $normalized = Str::upper(str_replace('=', '', $secret));
        $bits = '';

        foreach (str_split($normalized) as $character) {
            $position = strpos(self::Alphabet, $character);

            if ($position === false) {
                throw new InvalidArgumentException('The TOTP secret is not valid Base32.');
            }

            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';

        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $decoded .= chr(bindec($chunk));
            }
        }

        return $decoded;
    }
}
