<?php

namespace App\AdvisoryAi;

use Illuminate\Support\Str;
use InvalidArgumentException;

final class AiContextSanitizer
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function sanitize(UseCase $useCase, array $context): array
    {
        $safe = [];

        foreach ($useCase->allowedContextKeys() as $key) {
            if (! array_key_exists($key, $context) || ! $this->isAllowedValue($context[$key])) {
                continue;
            }

            $safe[$key] = $this->sanitizeValue($context[$key]);
        }

        return $safe;
    }

    public function sanitizeInstruction(string $instruction): string
    {
        $plainText = html_entity_decode(strip_tags($instruction), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plainText = preg_replace(
            '/[\x00-\x1F\x7F\x{202A}-\x{202E}\x{2066}-\x{2069}]+/u',
            ' ',
            $plainText,
        ) ?? '';
        $plainText = preg_replace(
            [
                '/\b(?:api[_-]?key|password|secret|token|recovery[_-]?code)\s*[:=]\s*\S+/iu',
                '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/iu',
                '/\b(?:https?:\/\/|www\.)\S+/iu',
                '/\bBearer\s+\S+/iu',
                '/(?<!\w)\+?\d[\d\s().-]{7,}\d(?!\w)/u',
                '/\b[A-Za-z0-9+\/_=-]{32,}\b/u',
            ],
            '[redacted]',
            $plainText,
        ) ?? '';
        $plainText = Str::squish($plainText);

        if ($plainText === '') {
            throw new InvalidArgumentException('AI advisory instructions must contain safe plain text.');
        }

        if (Str::length($plainText) > 1000) {
            throw new InvalidArgumentException('AI advisory instructions may not exceed 1000 characters.');
        }

        return $plainText;
    }

    private function isAllowedValue(mixed $value): bool
    {
        if (is_scalar($value) || $value === null) {
            return ! is_string($value) || Str::length($value) <= 2000;
        }

        if (! is_array($value) || ! array_is_list($value) || count($value) > 50) {
            return false;
        }

        foreach ($value as $item) {
            if (
                (! is_scalar($item) && $item !== null)
                || (is_string($item) && Str::length($item) > 500)
            ) {
                return false;
            }
        }

        return true;
    }

    private function sanitizeValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->sanitizeContextText($value);
        }

        if (is_array($value)) {
            return array_map(
                fn (mixed $item): mixed => is_string($item) ? $this->sanitizeContextText($item) : $item,
                $value,
            );
        }

        return $value;
    }

    private function sanitizeContextText(string $value): string
    {
        $plainText = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plainText = preg_replace('/[\x00-\x1F\x7F\x{202A}-\x{202E}\x{2066}-\x{2069}]+/u', ' ', $plainText) ?? '';

        return Str::squish($plainText);
    }
}
