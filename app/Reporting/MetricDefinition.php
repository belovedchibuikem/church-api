<?php

namespace App\Reporting;

final readonly class MetricDefinition
{
    public function __construct(
        public MetricKey $key,
        public string $label,
        public string $description,
        public string $sourcePolicy,
        public bool $containsPersonalData = false,
    ) {}
}
