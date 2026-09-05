<?php

namespace App\Support\Kca;

final class SyncKcaAdmissionLetterReference
{
    public static function inBody(string $body, string $referenceCode): string
    {
        if ($body === '' || $referenceCode === '' || strcasecmp($referenceCode, 'Pending') === 0) {
            return $body;
        }

        $updated = preg_replace('/Registration Number:\s*[^\n]+/i', 'Registration Number: '.$referenceCode, $body, 1);
        $updated = is_string($updated) ? $updated : $body;
        $updated = preg_replace('/Ref\.?\s*No\.?\s*:\s*[^\n]+/i', 'Ref. No.: '.$referenceCode, $updated, 1);
        $updated = is_string($updated) ? $updated : $body;
        $updated = preg_replace('/\{registration_number\}/i', $referenceCode, $updated);
        $updated = is_string($updated) ? $updated : $body;
        $updated = preg_replace('/\{reference_code\}/i', $referenceCode, $updated);

        return is_string($updated) ? $updated : $body;
    }
}
