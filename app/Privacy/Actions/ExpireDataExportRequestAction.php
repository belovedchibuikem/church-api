<?php

namespace App\Privacy\Actions;

use App\Exceptions\DataExportInvalidStateException;
use App\Models\DataSubjectRequest;
use App\Models\User;
use App\Privacy\DataSubjectRequestStatus;
use App\Privacy\DataSubjectRequestType;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class ExpireDataExportRequestAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(DataSubjectRequest $request, ?User $actor = null): DataSubjectRequest
    {
        return DB::transaction(function () use ($request, $actor): DataSubjectRequest {
            $locked = DataSubjectRequest::query()->lockForUpdate()->findOrFail($request->getKey());

            if ($locked->status === DataSubjectRequestStatus::Expired) {
                return $locked;
            }

            if (
                $locked->request_type !== DataSubjectRequestType::Export
                || $locked->status !== DataSubjectRequestStatus::Completed
                || $locked->export_expires_at === null
                || $locked->export_expires_at->isFuture()
            ) {
                throw new DataExportInvalidStateException('Only completed export artifacts past their approved expiry may expire.');
            }

            $locked->forceFill(['status' => DataSubjectRequestStatus::Expired])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'privacy.data_export.expired',
                actor: $actor,
                targetType: 'data_subject_request',
                targetId: $locked->public_id,
                metadata: ['status' => DataSubjectRequestStatus::Expired->value],
            ));

            return $locked;
        }, attempts: 3);
    }
}
