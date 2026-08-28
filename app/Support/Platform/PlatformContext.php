<?php

namespace App\Support\Platform;

use App\Support\Authorization\ScopeReference;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class PlatformContext
{
    public const string AllEnvironments = '*';

    public function __construct(
        public string $environment,
        public ?ScopeReference $scope = null,
    ) {
        if (
            $this->environment !== self::AllEnvironments
            && (
                Str::length($this->environment) > 50
                || ! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $this->environment)
            )
        ) {
            throw new InvalidArgumentException('Environments must be stable lowercase identifiers.');
        }
    }

    public function hash(): string
    {
        return hash('sha256', implode("\0", [
            $this->environment,
            $this->scope?->type ?? '',
            $this->scope?->key ?? '',
        ]));
    }

    /**
     * @return array<int, string>
     */
    public function candidateHashes(): array
    {
        $contexts = [$this];

        if ($this->environment !== self::AllEnvironments) {
            $contexts[] = new self(self::AllEnvironments, $this->scope);
        }

        if ($this->scope !== null) {
            $contexts[] = new self($this->environment);

            if ($this->environment !== self::AllEnvironments) {
                $contexts[] = new self(self::AllEnvironments);
            }
        }

        return collect($contexts)
            ->map(fn (self $context): string => $context->hash())
            ->unique()
            ->values()
            ->all();
    }
}
