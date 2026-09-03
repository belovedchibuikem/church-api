<?php

namespace App\Support\Kca;

use App\Kca\KcaAttendanceStatus;
use App\Models\KcaAttendance;
use App\Models\KcaEnrollment;
use App\Models\KcaLesson;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RecordKcaAttendanceAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(
        KcaEnrollment $enrollment,
        KcaLesson $lesson,
        KcaAttendanceStatus $status,
        CarbonImmutable $sessionOn,
        User $actor,
        bool $updateExisting = true,
    ): KcaAttendance {
        return DB::transaction(function () use ($enrollment, $lesson, $status, $sessionOn, $actor, $updateExisting): KcaAttendance {
            $lockedEnrollment = KcaEnrollment::query()->lockForUpdate()->findOrFail($enrollment->getKey());
            $lockedLesson = KcaLesson::query()->lockForUpdate()->findOrFail($lesson->getKey());
            $existing = KcaAttendance::query()
                ->whereBelongsTo($lockedEnrollment, 'enrollment')
                ->whereBelongsTo($lockedLesson, 'lesson')
                ->whereDate('session_on', $sessionOn->toDateString())
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if (! $updateExisting || $existing->status === $status) {
                    return $existing;
                }
                $existing->forceFill([
                    'status' => $status,
                    'recorded_by_user_id' => $actor->getKey(),
                    'recorded_at' => now()->utc(),
                ])->save();

                $this->recordAuditEvent->handle(new AuditEventData(
                    action: 'kca.attendance.updated',
                    actor: $actor,
                    targetType: 'kca_attendance',
                    targetId: $existing->public_id,
                    metadata: [
                        'enrollment_id' => $lockedEnrollment->public_id,
                        'lesson_id' => $lockedLesson->public_id,
                        'status' => $status->value,
                        'session_on' => $sessionOn->toDateString(),
                    ],
                ));

                return $existing->refresh();
            }

            $attendance = KcaAttendance::query()->create([
                'kca_enrollment_id' => $lockedEnrollment->getKey(),
                'kca_lesson_id' => $lockedLesson->getKey(),
                'status' => $status,
                'session_on' => $sessionOn,
                'recorded_by_user_id' => $actor->getKey(),
                'recorded_at' => now()->utc(),
            ]);

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.attendance.recorded',
                actor: $actor,
                targetType: 'kca_attendance',
                targetId: $attendance->public_id,
                metadata: [
                    'enrollment_id' => $lockedEnrollment->public_id,
                    'lesson_id' => $lockedLesson->public_id,
                    'status' => $status->value,
                    'session_on' => $sessionOn->toDateString(),
                ],
            ));

            return $attendance;
        }, attempts: 3);
    }
}
