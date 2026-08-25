<?php

namespace App\Support\Audit;

use App\Models\User;

final readonly class AuditEventData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $action,
        public ?User $actor = null,
        public ?string $targetType = null,
        public ?string $targetId = null,
        public ?string $scopeType = null,
        public ?string $scopeId = null,
        public array $metadata = [],
    ) {}
}
