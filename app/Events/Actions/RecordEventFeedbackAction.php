<?php

namespace App\Events\Actions;

use App\Events\EventRegistrationStatus;
use App\Models\EventFeedback;
use App\Models\EventRegistration;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RecordEventFeedbackAction
{
    public function __construct(private readonly RecordAuditEventAction $recordAuditEvent) {}

    public function handle(EventRegistration $registration, int $rating, ?User $actor = null): EventFeedback
    {
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('Event feedback rating must be between 1 and 5.');
        }

        return DB::transaction(function () use ($registration, $rating, $actor): EventFeedback {
            $locked = EventRegistration::query()->lockForUpdate()->findOrFail($registration->getKey());
            $existing = EventFeedback::query()->whereBelongsTo($locked, 'registration')->first();
            if ($existing !== null) {
                return $existing;
            }
            if ($locked->status !== EventRegistrationStatus::Attended) {
                throw new DomainException('Feedback requires recorded attendance.');
            }
            $feedback = new EventFeedback;
            $feedback->forceFill(['event_registration_id' => $locked->getKey(), 'rating' => $rating, 'submitted_at' => now()->utc()])->save();
            $locked->forceFill(['status' => EventRegistrationStatus::FeedbackRecorded])->save();
            $this->recordAuditEvent->handle(new AuditEventData(action: 'events.feedback.recorded', actor: $actor, targetType: 'event_registration', targetId: $locked->public_id, scopeType: 'ministry_event', scopeId: $locked->event->public_id, metadata: ['rating' => $rating]));

            return $feedback;
        }, attempts: 3);
    }
}
