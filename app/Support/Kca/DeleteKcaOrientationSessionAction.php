<?php

namespace App\Support\Kca;

use App\Models\KcaOrientationSession;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class DeleteKcaOrientationSessionAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(KcaOrientationSession $session, User $actor): void
    {
        DB::transaction(function () use ($session, $actor): void {
            $locked = KcaOrientationSession::query()->lockForUpdate()->findOrFail($session->getKey());
            $publicId = $locked->public_id;

            $locked->delete();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.orientation_session.deleted',
                actor: $actor,
                targetType: 'kca_orientation_session',
                targetId: $publicId,
            ));
        }, attempts: 3);
    }
}
