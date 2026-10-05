<?php

namespace App\Support\Kca;

use App\Models\KcaAttendance;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class DeleteKcaAttendanceAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(KcaAttendance $attendance, User $actor): void
    {
        DB::transaction(function () use ($attendance, $actor): void {
            $locked = KcaAttendance::query()->lockForUpdate()->findOrFail($attendance->getKey());
            $locked->loadMissing('enrollment', 'lesson');
            $publicId = $locked->public_id;
            $locked->delete();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.attendance.reversed',
                actor: $actor,
                targetType: 'kca_attendance',
                targetId: $publicId,
                metadata: [
                    'enrollment_id' => $locked->enrollment?->public_id,
                    'lesson_id' => $locked->lesson?->public_id,
                    'session_on' => $locked->session_on?->toDateString(),
                ],
            ));
        });
    }
}
