<?php

namespace App\Support\Health;

final readonly class ReadinessResult
{
    /**
     * @param  array<string, string>  $checks
     */
    public function __construct(
        public bool $ready,
        public array $checks,
    ) {}

    /**
     * @return array{status: string, checks: array<string, string>}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->ready ? 'ready' : 'not_ready',
            'checks' => $this->checks,
        ];
    }
}
