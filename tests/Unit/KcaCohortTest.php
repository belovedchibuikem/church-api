<?php

namespace Tests\Unit;

use App\Models\KcaCohort;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KcaCohortTest extends TestCase
{
    use RefreshDatabase;

    public function test_lifecycle_status_is_upcoming_before_start_date(): void
    {
        $cohort = KcaCohort::factory()->create([
            'starts_on' => CarbonImmutable::today()->addWeek(),
            'ends_on' => CarbonImmutable::today()->addMonths(2),
        ]);

        $this->assertSame('upcoming', $cohort->lifecycleStatus());
    }

    public function test_lifecycle_status_is_completed_after_end_date(): void
    {
        $cohort = KcaCohort::factory()->create([
            'starts_on' => CarbonImmutable::today()->subMonths(3),
            'ends_on' => CarbonImmutable::today()->subWeek(),
        ]);

        $this->assertSame('completed', $cohort->lifecycleStatus());
    }

    public function test_lifecycle_status_is_active_between_start_and_end(): void
    {
        $cohort = KcaCohort::factory()->create([
            'starts_on' => CarbonImmutable::today()->subWeek(),
            'ends_on' => CarbonImmutable::today()->addMonths(2),
        ]);

        $this->assertSame('active', $cohort->lifecycleStatus());
    }
}
