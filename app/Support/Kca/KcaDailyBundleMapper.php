<?php

namespace App\Support\Kca;

use InvalidArgumentException;

final class KcaDailyBundleMapper
{
    /**
     * Evenly assign sequenced lessons to 1..durationDays.
     * 12 lessons / 7 days → 2,2,2,2,2,1,1. 18 / 10 → 2,2,2,2,2,2,2,2,1,1.
     *
     * @return list<int>
     */
    public static function evenDistribution(int $lessonCount, int $durationDays): array
    {
        if ($lessonCount < 1) {
            throw new InvalidArgumentException('A module must contain at least one lesson.');
        }
        if ($durationDays < 1) {
            throw new InvalidArgumentException('Module duration_days must be at least 1.');
        }
        if ($lessonCount < $durationDays) {
            $mapping = [];
            for ($lesson = 1; $lesson <= $lessonCount; $lesson++) {
                $mapping[] = $lesson;
            }

            return $mapping;
        }

        $base = intdiv($lessonCount, $durationDays);
        $remainder = $lessonCount % $durationDays;
        $mapping = [];
        for ($day = 1; $day <= $durationDays; $day++) {
            $count = $base + ($day <= $remainder ? 1 : 0);
            for ($i = 0; $i < $count; $i++) {
                $mapping[] = $day;
            }
        }

        return $mapping;
    }
}
