<?php

namespace App\AdvisoryAi;

final readonly class AdvisoryRequest
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public Assistant $assistant,
        public UseCase $useCase,
        public string $instruction,
        public array $context = [],
    ) {}
}
