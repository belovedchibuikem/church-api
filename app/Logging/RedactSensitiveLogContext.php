<?php

namespace App\Logging;

use Illuminate\Support\Str;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class RedactSensitiveLogContext implements ProcessorInterface
{
    private const array SENSITIVE_KEY_FRAGMENTS = [
        'address',
        'api_key',
        'authorization',
        'birth_date',
        'card_number',
        'cookie',
        'counselling',
        'credential',
        'csrf',
        'cvc',
        'cvv',
        'date_of_birth',
        'email',
        'first_name',
        'full_name',
        'last_name',
        'mfa',
        'middle_name',
        'mobile',
        'narrative',
        'otp',
        'password',
        'pastoral',
        'phone',
        'private_key',
        'recovery_code',
        'safeguarding',
        'secret',
        'session_id',
        'token',
        'xsrf',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            context: $this->redact($record->context),
            extra: $this->redact($record->extra),
        );
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $values[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalizedKey = Str::of($key)
            ->lower()
            ->replace(['-', '.', ' '], '_')
            ->toString();

        return Str::contains($normalizedKey, self::SENSITIVE_KEY_FRAGMENTS);
    }
}
