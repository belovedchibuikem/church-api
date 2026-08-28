<?php

namespace Tests\Unit\Reporting;

use App\Reporting\MetricDefinitionRegistry;
use App\Reporting\MetricKey;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MetricDefinitionRegistryTest extends TestCase
{
    public function test_every_canonical_metric_has_one_definition(): void
    {
        $definitions = (new MetricDefinitionRegistry)->all();

        $this->assertCount(count(MetricKey::cases()), $definitions);
        $this->assertCount(
            count($definitions),
            array_unique(array_map(fn ($definition) => $definition->key->value, $definitions)),
        );
    }

    public function test_finance_derived_press_sales_use_reconciled_values(): void
    {
        $definition = (new MetricDefinitionRegistry)->get(MetricKey::PressSales);

        $this->assertSame('reconciled_sum', $definition->sourcePolicy);
    }

    public function test_unknown_metric_keys_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new MetricDefinitionRegistry)->get('unknown.metric');
    }
}
