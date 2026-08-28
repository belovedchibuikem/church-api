<?php

namespace App\Events\Actions;

use App\Events\EventRegistrationStatus;
use App\Models\EventAttendance;
use App\Models\EventRegistration;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use DomainException;
use Illuminate\Support\Facades\DB;

class RecordEventAttendanceAction
{
    public function __construct(private readonly RecordAuditEventAction $recordAuditEvent) {}

    public function handle(EventRegistration $registration, string $sourceCode, ?User $actor = null): EventAttendance
    {
        return DB::transaction(function () use ($registration, $sourceCode, $actor): EventAttendance {
            $locked = EventRegistration::query()->lockForUpdate()->findOrFail($registration->getKey());
            $existing = EventAttendance::query()->whereBelongsTo($locked, 'registration')->first();
            if ($existing !== null) {
                return $existing;
            }
            if ($locked->status !== EventRegistrationStatus::Confirmed) {
                throw new DomainException('Only confirmed event registrations may attend.');
            }
            $attendance = new EventAttendance;
            $attendance->forceFill(['event_registration_id' => $locked->getKey(), 'source_code' => $sourceCode, 'attended_at' => now()->utc()])->save();
            $locked->forceFill(['status' => EventRegistrationStatus::Attended])->save();
            $this->recordAuditEvent->handle(new AuditEventData(action: 'events.attendance.recorded', actor: $actor, targetType: 'event_registration', targetId: $locked->public_id, scopeType: 'ministry_event', scopeId: $locked->event->public_id, metadata: ['source_code' => $sourceCode]));

            return $attendance;
        }, attempts: 3);
    }
}
