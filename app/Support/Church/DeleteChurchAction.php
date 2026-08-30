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

            if (
                $lockedChurch->homeChurches()->exists()
                || $lockedChurch->homeChurchApplications()->exists()
                || $lockedChurch->memberships()->exists()
                || $lockedChurch->firstTimers()->exists()
            ) {
                throw new InvalidArgumentException(
                    'This church cannot be deleted while home churches, applications, memberships, or first-timers are linked to it.',
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
