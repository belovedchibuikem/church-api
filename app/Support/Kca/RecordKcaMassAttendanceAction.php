<?php

namespace App\Support\Kca;

use App\Kca\KcaAttendanceStatus;
use App\Models\KcaAttendance;
use App\Models\KcaEnrollment;
use App\Models\KcaLesson;
use App\Models\User;
use App\Support\Identity\PersonDisplayName;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class RecordKcaMassAttendanceAction
{
    public function __construct(private RecordKcaAttendanceAction $recordAttendance) {}

    /**
     * @param  list<array{enrollment_id: string, status: string}>  $records
     * @return array{recorded: int, updated: int, rows: list<array<string, mixed>>}
     */
    public function handle(
        KcaLesson $lesson,
        CarbonImmutable $sessionOn,
        array $records,
        User $actor,
    ): array {
        $recorded = 0;
        $updated = 0;
        $rows = [];

        foreach ($records as $record) {
            $enrollment = KcaEnrollment::query()
                ->where('public_id', $record['enrollment_id'])
                ->firstOrFail();
            $before = KcaAttendance::query()
                ->where('kca_enrollment_id', $enrollment->getKey())
                ->where('kca_lesson_id', $lesson->getKey())
                ->whereDate('session_on', $sessionOn->toDateString())
                ->first();

            $attendance = $this->recordAttendance->handle(
                $enrollment,
                $lesson,
                KcaAttendanceStatus::from($record['status']),
                $sessionOn,
                $actor,
                true,
            );

            if ($before === null) {
                $recorded++;
            } elseif ($before->status !== $attendance->status) {
                $updated++;
            }

            $attendance->load([
                'enrollment:id,public_id,registration_number',
                ...PersonDisplayName::eager('enrollment.person'),
                'lesson:id,public_id,title,code',
            ]);
            $rows[] = [
                'id' => $attendance->public_id,
                'enrollment_id' => $enrollment->public_id,
                'status' => $attendance->status instanceof KcaAttendanceStatus
                    ? $attendance->status->value
                    : (string) $attendance->status,
                'session_on' => $sessionOn->toDateString(),
            ];
        }

        return [
            'recorded' => $recorded,
            'updated' => $updated,
            'rows' => $rows,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function roster(KcaLesson $lesson, CarbonImmutable $sessionOn, ?string $cohortId = null): Collection
    {
        $query = KcaEnrollment::query()
            ->with([...PersonDisplayName::eager('person'), 'cohort:id,public_id,name'])
            ->orderBy('registration_number');

        if ($cohortId) {
            $query->whereHas('cohort', fn ($q) => $q->where('public_id', $cohortId));
        }

        $existing = KcaAttendance::query()
            ->where('kca_lesson_id', $lesson->getKey())
            ->whereDate('session_on', $sessionOn->toDateString())
            ->get()
            ->keyBy('kca_enrollment_id');

        return $query->get()->map(function (KcaEnrollment $enrollment) use ($existing): array {
            $row = $existing->get($enrollment->getKey());

            return [
                'enrollment_id' => $enrollment->public_id,
                'registration_number' => $enrollment->registration_number,
                'student_name' => PersonDisplayName::of($enrollment->person),
                'cohort_name' => $enrollment->cohort?->name,
                'status' => $row
                    ? ($row->status instanceof KcaAttendanceStatus ? $row->status->value : (string) $row->status)
                    : null,
                'attendance_id' => $row?->public_id,
            ];
        })->values();
    }
}
