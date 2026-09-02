<?php

namespace Tests\Unit;

use App\Models\HomeChurchAttendanceRecord;
use PHPUnit\Framework\TestCase;

class HomeChurchAttendanceRecordTest extends TestCase
{
    public function test_counts_from_payload_maps_gender_breakdown_and_total_adults(): void
    {
        $counts = HomeChurchAttendanceRecord::countsFromPayload([
            'males' => 11,
            'females' => 7,
            'children' => 7,
        ]);

        $this->assertSame(11, $counts['males']);
        $this->assertSame(7, $counts['females']);
        $this->assertSame(7, $counts['children']);
        $this->assertSame(18, $counts['adults']);
    }

    public function test_counts_from_payload_maps_legacy_adults_to_males(): void
    {
        $counts = HomeChurchAttendanceRecord::countsFromPayload([
            'adults' => 18,
            'children' => 7,
        ]);

        $this->assertSame(18, $counts['males']);
        $this->assertSame(0, $counts['females']);
        $this->assertSame(7, $counts['children']);
        $this->assertSame(18, $counts['adults']);
    }
}
