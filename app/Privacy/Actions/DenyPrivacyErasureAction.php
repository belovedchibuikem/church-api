<?php

namespace App\Privacy\Actions;

use App\Exceptions\DataExportExecutionDeniedException;
use App\Models\DataSubjectRequest;
use App\Models\User;
use App\Privacy\Contracts\DataSubjectRequestExecutionPolicy;
use App\Privacy\DataSubjectRequestStatus;
use App\Privacy\DataSubjectRequestType;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class DenyPrivacyErasureAction
{
    public function __construct(
        private DataSubjectRequestExecutionPolicy $executionPolicy,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(DataSubjectRequest $request, User $actor, bool $recordDenial): DataSubjectRequest
    {
        return DB::transaction(function () use ($request, $actor, $recordDenial): DataSubjectRequest {
            $locked = DataSubjectRequest::query()->lockForUpdate()->findOrFail($request->getKey());

            if ($locked->request_type !== DataSubjectRequestType::Deletion) {
                throw new InvalidArgumentException('Only deletion requests can be sent through the erasure path.');
            }

            $decision = $this->executionPolicy->decide($locked, $actor);
            if ($decision->allowed) {
                throw new InvalidArgumentException('Erasure execution is not implemented.');
            }

            if (! $recordDenial) {
                throw new DataExportExecutionDeniedException($decision->reasonCode);
            }

            $locked->status = DataSubjectRequestStatus::Rejected;
            $locked->reviewed_at = now()->utc();
            $locked->reviewedBy()->associate($actor);
            $locked->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'privacy.data_subject_request.erasure_denied',
                actor: $actor,
                targetType: 'data_subject_request',
                targetId: $locked->public_id,
                metadata: [
                    'reason_code' => $decision->reasonCode,
                    'status' => $locked->status->value,
                ],
            ));

            return $locked;
        }, attempts: 3);
    }
}
