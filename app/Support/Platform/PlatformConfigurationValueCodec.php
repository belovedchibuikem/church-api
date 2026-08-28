<?php

namespace App\Support\Platform;

use App\Platform\ConfigurationClassification;
use App\Platform\ConfigurationValueType;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;

class PlatformConfigurationValueCodec
{
    public function __construct(private Encrypter $encrypter) {}

    /**
     * @throws JsonException
     */
    public function encode(
        ConfigurationValueType $type,
        ConfigurationClassification $classification,
        mixed $value,
    ): string {
        $serializedValue = match ($type) {
            ConfigurationValueType::String => $this->encodeString($value),
            ConfigurationValueType::Integer => $this->encodeInteger($value),
            ConfigurationValueType::Boolean => $this->encodeBoolean($value),
            ConfigurationValueType::Json => $this->encodeJson($value),
        };

        if ($classification === ConfigurationClassification::Confidential) {
            return $this->encrypter->encryptString($serializedValue);
        }

        return $serializedValue;
    }

    /**
     * @throws JsonException
     */
    public function decode(
        ConfigurationValueType $type,
        ConfigurationClassification $classification,
        string $storedValue,
    ): mixed {
        $serializedValue = $classification === ConfigurationClassification::Confidential
            ? $this->encrypter->decryptString($storedValue)
            : $storedValue;

        return match ($type) {
            ConfigurationValueType::String => $serializedValue,
            ConfigurationValueType::Integer => (int) $serializedValue,
            ConfigurationValueType::Boolean => $serializedValue === '1',
            ConfigurationValueType::Json => json_decode(
                $serializedValue,
                true,
                flags: JSON_THROW_ON_ERROR,
            ),
        };
    }

    private function encodeString(mixed $value): string
    {
        if (! is_string($value) || Str::length($value) > 65535) {
            throw new InvalidArgumentException('String configuration values must not exceed 65,535 characters.');
        }

        return $value;
    }

    private function encodeInteger(mixed $value): string
    {
        if (! is_int($value)) {
            throw new InvalidArgumentException('Integer configuration values must be integers.');
        }

        return (string) $value;
    }

    private function encodeBoolean(mixed $value): string
    {
        if (! is_bool($value)) {
            throw new InvalidArgumentException('Boolean configuration values must be booleans.');
        }

        return $value ? '1' : '0';
    }

    /**
     * @throws JsonException
     */
    private function encodeJson(mixed $value): string
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException('JSON configuration values must be arrays.');
        }

        $encodedValue = json_encode($value, JSON_THROW_ON_ERROR);

        if (Str::length($encodedValue) > 65535) {
            throw new InvalidArgumentException('JSON configuration values must not exceed 65,535 characters.');
        }

        return $encodedValue;
    }
}
