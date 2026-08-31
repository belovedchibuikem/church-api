<?php

namespace App\Church;

use DateTimeImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class HomeChurchMeetingSlot
{
    public string $time;

    public string $activity;

    public function __construct(
        public MeetingDay $day,
        string $time,
        string $activity,
    ) {
        $this->time = $this->normalizeTime($time);
        $this->activity = $this->normalizeActivity($activity, $day);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $dayRaw = Str::lower(trim((string) ($input['day'] ?? '')));
        $day = MeetingDay::tryFrom($dayRaw);

        if ($day === null) {
            throw new InvalidArgumentException('Each meeting schedule requires a valid day of week.');
        }

        return new self(
            $day,
            (string) ($input['time'] ?? ''),
            (string) ($input['activity'] ?? ''),
        );
    }

    public static function defaultActivity(MeetingDay $day): string
    {
        return match ($day) {
            MeetingDay::Sunday => 'Main service',
            MeetingDay::Wednesday => 'Midweek service',
            MeetingDay::Friday => 'Prayer meeting',
            MeetingDay::Saturday => 'Fellowship',
            default => 'Gathering',
        };
    }

    /** @return array{day: string, time: string, activity: string} */
    public function toArray(): array
    {
        return [
            'day' => $this->day->value,
            'time' => substr($this->time, 0, 5),
            'activity' => $this->activity,
        ];
    }

    private function normalizeTime(string $value): string
    {
        $normalized = Str::of($value)->trim()->toString();
        $withSeconds = DateTimeImmutable::createFromFormat('!H:i:s', $normalized);
        if ($withSeconds !== false && $withSeconds->format('H:i:s') === $normalized) {
            return $normalized;
        }

        $time = DateTimeImmutable::createFromFormat('!H:i', $normalized);
        if ($time === false || $time->format('H:i') !== $normalized) {
            throw new InvalidArgumentException('Meeting time must use 24-hour HH:MM format.');
        }

        return $time->format('H:i:s');
    }

    private function normalizeActivity(string $value, MeetingDay $day): string
    {
        $activity = Str::squish($value);
        if ($activity === '') {
            return self::defaultActivity($day);
        }

        if (Str::length($activity) > 80) {
            throw new InvalidArgumentException('Meeting activity labels must contain at most 80 characters.');
        }

        return $activity;
    }
}
