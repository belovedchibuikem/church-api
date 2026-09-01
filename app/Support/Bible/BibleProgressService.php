<?php

namespace App\Support\Bible;

use App\Models\BiblePlanDayCompletion;
use App\Models\BiblePlanEnrollment;
use App\Models\BibleReadingPosition;
use App\Models\Person;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class BibleProgressService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(Person $person): array
    {
        $enrollment = BiblePlanEnrollment::query()
            ->where('person_id', $person->getKey())
            ->where('status', 'active')
            ->first();

        $position = BibleReadingPosition::query()
            ->where('person_id', $person->getKey())
            ->first();

        return [
            'version' => BibleTextStore::version(),
            'plans' => BibleReadingPlanGenerator::summaries(),
            'position' => $this->positionPayload($position),
            'enrollment' => $enrollment === null ? null : $this->enrollmentPayload($enrollment),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function enroll(Person $person, string $planCode, ?string $startedOn, ?string $timezone): array
    {
        $plan = BibleReadingPlanGenerator::plan($planCode);
        abort_unless($plan !== null, 422, 'Unknown Bible reading plan.');

        $zone = $timezone ?: 'UTC';
        $start = $startedOn !== null && $startedOn !== ''
            ? CarbonImmutable::parse($startedOn, $zone)->startOfDay()
            : CarbonImmutable::now($zone)->startOfDay();

        $enrollment = DB::transaction(function () use ($person, $planCode, $start, $zone): BiblePlanEnrollment {
            BiblePlanEnrollment::query()
                ->where('person_id', $person->getKey())
                ->where('status', 'active')
                ->update(['status' => 'abandoned']);

            $record = new BiblePlanEnrollment;
            $record->forceFill([
                'person_id' => $person->getKey(),
                'plan_code' => $planCode,
                'started_on' => $start->toDateString(),
                'timezone' => $zone,
                'status' => 'active',
            ])->save();

            return $record;
        });

        return $this->snapshot($person);
    }

    /**
     * @return array<string, mixed>
     */
    public function completeDay(Person $person, string $enrollmentId, int $dayNumber): array
    {
        $enrollment = BiblePlanEnrollment::query()
            ->where('person_id', $person->getKey())
            ->where('public_id', $enrollmentId)
            ->where('status', 'active')
            ->firstOrFail();

        $plan = BibleReadingPlanGenerator::plan($enrollment->plan_code);
        abort_unless($plan !== null, 422, 'Unknown Bible reading plan.');
        abort_unless($dayNumber >= 1 && $dayNumber <= $plan['days'], 422, 'That plan day does not exist.');

        $state = $this->schedule($enrollment, $plan);
        abort_unless($dayNumber <= $state['calendar_day'], 422, 'That day is not due yet.');

        $existing = BiblePlanDayCompletion::query()
            ->where('enrollment_id', $enrollment->getKey())
            ->where('day_number', $dayNumber)
            ->first();
        if ($existing === null) {
            $completion = new BiblePlanDayCompletion;
            $completion->forceFill([
                'enrollment_id' => $enrollment->getKey(),
                'day_number' => $dayNumber,
                'completed_at' => now(),
            ])->save();
        }

        $completedCount = BiblePlanDayCompletion::query()
            ->where('enrollment_id', $enrollment->getKey())
            ->count();
        if ($completedCount >= $plan['days']) {
            $enrollment->forceFill(['status' => 'completed'])->save();
        }

        $due = $plan['passages_per_day'][$dayNumber - 1][0] ?? null;
        if (is_array($due)) {
            $this->rememberPosition($person, $due['book_id'], $due['chapter']);
        }

        return $this->snapshot($person);
    }

    /**
     * @return array<string, mixed>
     */
    public function rememberPosition(Person $person, string $bookId, int $chapter): array
    {
        $book = BibleCanon::bookByIdOrSlug($bookId);
        abort_unless($book !== null, 422, 'Unknown Bible book.');
        abort_unless($chapter >= 1 && $chapter <= $book['chapters'], 422, 'Unknown Bible chapter.');

        $position = BibleReadingPosition::query()
            ->where('person_id', $person->getKey())
            ->first();
        if ($position === null) {
            $position = new BibleReadingPosition;
        }
        $position->forceFill([
            'person_id' => $person->getKey(),
            'book_id' => $book['id'],
            'chapter' => $chapter,
        ])->save();

        return [
            'position' => $this->positionPayload($position),
        ];
    }

    /**
     * @param  array{days: int, passages_per_day: list<list<array<string, mixed>>>}  $plan
     * @return array{calendar_day: int, timezone: string, today: CarbonImmutable}
     */
    private function schedule(BiblePlanEnrollment $enrollment, array $plan): array
    {
        $timezone = $enrollment->timezone ?: 'UTC';
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $start = CarbonImmutable::parse($enrollment->started_on->toDateString(), $timezone)->startOfDay();
        $elapsed = $start->diffInDays($today) + 1;
        $calendarDay = min($plan['days'], max(1, (int) $elapsed));

        return [
            'calendar_day' => $calendarDay,
            'timezone' => $timezone,
            'today' => $today,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function positionPayload(?BibleReadingPosition $position): ?array
    {
        if ($position === null) {
            return null;
        }
        $book = BibleCanon::bookByIdOrSlug($position->book_id);
        if ($book === null) {
            return null;
        }

        return [
            'book_id' => $book['id'],
            'book_slug' => $book['slug'],
            'book_name' => $book['name'],
            'chapter' => (int) $position->chapter,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function enrollmentPayload(BiblePlanEnrollment $enrollment): array
    {
        $plan = BibleReadingPlanGenerator::plan($enrollment->plan_code);
        abort_unless($plan !== null, 422, 'Unknown Bible reading plan.');
        $schedule = $this->schedule($enrollment, $plan);
        $completed = $enrollment->completions()->pluck('day_number')->map(fn ($day) => (int) $day)->all();
        $completedSet = array_flip($completed);
        $dueDay = null;
        for ($day = 1; $day <= $schedule['calendar_day']; $day++) {
            if (! isset($completedSet[$day])) {
                $dueDay = $day;
                break;
            }
        }

        $overdue = 0;
        for ($day = 1; $day < $schedule['calendar_day']; $day++) {
            if (! isset($completedSet[$day])) {
                $overdue++;
            }
        }

        $percent = $plan['days'] === 0 ? 0 : (int) round((count($completed) / $plan['days']) * 100);

        return [
            'id' => $enrollment->public_id,
            'plan_code' => $enrollment->plan_code,
            'plan_name' => $plan['name'],
            'status' => $enrollment->status,
            'started_on' => $enrollment->started_on->toDateString(),
            'timezone' => $schedule['timezone'],
            'day_count' => $plan['days'],
            'calendar_day' => $schedule['calendar_day'],
            'completed_days' => count($completed),
            'percent' => min(100, $percent),
            'overdue_days' => $overdue,
            'is_catching_up' => $overdue > 0,
            'today' => [
                'day_number' => $schedule['calendar_day'],
                'passages' => $plan['passages_per_day'][$schedule['calendar_day'] - 1],
                'completed' => isset($completedSet[$schedule['calendar_day']]),
            ],
            'due' => $dueDay === null ? null : [
                'day_number' => $dueDay,
                'passages' => $plan['passages_per_day'][$dueDay - 1],
                'completed' => false,
            ],
        ];
    }
}
