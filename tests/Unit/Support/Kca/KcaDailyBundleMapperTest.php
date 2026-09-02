<?php

namespace Tests\Unit\Support\Kca;

use App\Support\Kca\KcaDailyBundleMapper;
use InvalidArgumentException;
use Tests\TestCase;

class KcaDailyBundleMapperTest extends TestCase
{
    public function test_even_distribution_spreads_many_lessons_across_fewer_days(): void
    {
        $this->assertSame(
            [1, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 7],
            KcaDailyBundleMapper::evenDistribution(12, 7),
        );
        $this->assertSame(
            [1, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 6, 7, 7, 8, 8, 9, 10],
            KcaDailyBundleMapper::evenDistribution(18, 10),
        );
    }

    public function test_even_distribution_assigns_one_lesson_per_day_when_duration_exceeds_lesson_count(): void
    {
        $this->assertSame([1, 2], KcaDailyBundleMapper::evenDistribution(2, 7));
        $this->assertSame([1, 2, 3], KcaDailyBundleMapper::evenDistribution(3, 10));
    }

    public function test_even_distribution_rejects_invalid_inputs(): void
    {
        $this->expectException(InvalidArgumentException::class);
        KcaDailyBundleMapper::evenDistribution(0, 7);
    }
}
