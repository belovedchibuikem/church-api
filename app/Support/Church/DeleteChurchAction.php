<?php

namespace App\Support\Church;

use App\Models\Church;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeleteChurchAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(Church $church, ?User $actor = null): void
    {
        DB::transaction(function () use ($church, $actor): void {
            $lockedChurch = Church::query()->lockForUpdate()->findOrFail($church->getKey());

            $homeChurches = $lockedChurch->homeChurches()->count();
            $applications = $lockedChurch->homeChurchApplications()->count();
            $memberships = $lockedChurch->memberships()->count();
            $firstTimers = $lockedChurch->firstTimers()->count();
            if ($homeChurches > 0 || $applications > 0 || $memberships > 0 || $firstTimers > 0) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Church "%s" cannot be deleted while it has %d home churches, %d applications, %d memberships, and %d first-timers. Reassign or archive those records first.',
                        $lockedChurch->name,
                        $homeChurches,
                        $applications,
                        $memberships,
                        $firstTimers,
                    ),
                );
            }

            $publicId = $lockedChurch->public_id;
            $lockedChurch->delete();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'church.deleted',
                actor: $actor,
                targetType: 'church',
                targetId: $publicId,
                scopeType: 'church',
                scopeId: $publicId,
            ));
        }, attempts: 3);
    }
}
