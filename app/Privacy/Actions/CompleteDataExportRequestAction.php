<?php

namespace App\Privacy\Actions;

use App\Exceptions\DataExportExecutionDeniedException;
use App\Exceptions\DataExportInvalidStateException;
use App\Files\FileAssetClassification;
use App\Files\FileAssetStatus;
use App\Models\DataSubjectRequest;
use App\Models\FileAsset;
use App\Models\User;
use App\Privacy\Contracts\DataSubjectRequestExecutionPolicy;
use App\Privacy\DataSubjectRequestStatus;
use App\Privacy\DataSubjectRequestType;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class CompleteDataExportRequestAction
{
    public function __construct(
        private DataSubjectRequestExecutionPolicy $executionPolicy,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        DataSubjectRequest $request,
        FileAsset $fileAsset,
        CarbonInterface $expiresAt,
        User $actor,
    ): DataSubjectRequest {
        $expiresAt = $expiresAt->toImmutable()->utc()->startOfSecond();

        if (! $expiresAt->isFuture()) {
            throw new DataExportInvalidStateException('Data export artifacts require a future expiry.');
        }

        return DB::transaction(function () use ($request, $fileAsset, $expiresAt, $actor): DataSubjectRequest {
            $locked = DataSubjectRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            $lockedFile = FileAsset::query()->lockForUpdate()->findOrFail($fileAsset->getKey());

            if ($locked->status === DataSubjectRequestStatus::Completed) {
                if (
                    $locked->export_file_asset_id !== $lockedFile->getKey()
                    || ! $locked->export_expires_at?->equalTo($expiresAt)
                ) {
                    throw new DataExportInvalidStateException('Completed export artifacts cannot be replaced.');
                }

                return $locked;
            }

            if (
                $locked->request_type !== DataSubjectRequestType::Export
                || $locked->status !== DataSubjectRequestStatus::Processing
            ) {
                throw new DataExportInvalidStateException('Only processing export requests may be completed.');
            }

            $decision = $this->executionPolicy->decide($locked, $actor);

            if (! $decision->allowed) {
                throw new DataExportExecutionDeniedException($decision->reasonCode);
            }

            if (
                $lockedFile->owner_person_id !== $locked->person_id
                || $lockedFile->status !== FileAssetStatus::Available
                || ! in_array($lockedFile->classification, [
                    FileAssetClassification::Confidential,
                    FileAssetClassification::Restricted,
                ], true)
            ) {
                throw new DataExportInvalidStateException('The export artifact must be an available private file owned by the data subject.');
            }

            $locked->forceFill([
                'export_file_asset_id' => $lockedFile->getKey(),
                'status' => DataSubjectRequestStatus::Completed,
                'completed_at' => now()->utc(),
                'export_expires_at' => $expiresAt,
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'privacy.data_export.completed',
                actor: $actor,
                targetType: 'data_subject_request',
                targetId: $locked->public_id,
                metadata: [
                    'classification' => $lockedFile->classification->value,
                    'status' => DataSubjectRequestStatus::Completed->value,
                ],
            ));

            return $locked;
        }, attempts: 3);
    }
}
