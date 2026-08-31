<?php

namespace App\Support\Church;

use App\Church\HomeChurchMeetingSlot;
use App\Church\MeetingDay;
use InvalidArgumentException;

final class HomeChurchMeetingSchedules
{
    /**
     * @param  list<array<string, mixed>>|null  $raw
     * @return list<HomeChurchMeetingSlot>
     */
    public static function normalize(?array $raw, MeetingDay $fallbackDay, string $fallbackTime): array
    {
        $slots = [];
        if (is_array($raw) && $raw !== []) {
            foreach ($raw as $index => $row) {
                if (! is_array($row)) {
                    throw new InvalidArgumentException("Meeting schedule {$index} is invalid.");
                }
                $slots[] = HomeChurchMeetingSlot::fromArray($row);
            }
        } else {
            $slots[] = new HomeChurchMeetingSlot($fallbackDay, $fallbackTime, HomeChurchMeetingSlot::defaultActivity($fallbackDay));
        }

        $seen = [];
        $unique = [];
        foreach ($slots as $slot) {
            if (isset($seen[$slot->day->value])) {
                throw new InvalidArgumentException('Each meeting day can only be scheduled once. Assign a distinct time per day.');
            }
            $seen[$slot->day->value] = true;
            $unique[] = $slot;
        }

        return $unique;
    }

    /**
     * @param  list<HomeChurchMeetingSlot>  $slots
     * @return list<array{day: string, time: string, activity: string}>
     */
    public static function toStorage(array $slots): array
    {
        return array_map(static fn (HomeChurchMeetingSlot $slot): array => $slot->toArray(), $slots);
    }
}
