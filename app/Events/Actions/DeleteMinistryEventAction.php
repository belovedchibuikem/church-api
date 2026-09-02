<?php

namespace App\Events\Actions;

use App\Models\MinistryEvent;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use DomainException;
use Illuminate\Support\Facades\DB;

class DeleteMinistryEventAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(MinistryEvent $event, User $actor): void
    {
        DB::transaction(function () use ($event, $actor): void {
            $locked = MinistryEvent::query()->lockForUpdate()->findOrFail($event->getKey());
            $publicId = $locked->public_id;

            if ($locked->registrations()->exists()) {
                throw new DomainException('Events with registrations cannot be deleted. Unpublish the event instead.');
            }

            $locked->delete();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'events.event.deleted',
                actor: $actor,
                targetType: 'ministry_event',
                targetId: $publicId,
            ));
        }, attempts: 3);
    }
}
