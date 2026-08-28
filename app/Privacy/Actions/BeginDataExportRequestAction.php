<?php

namespace App\Privacy\Actions;

use App\Exceptions\DataExportExecutionDeniedException;
use App\Exceptions\DataExportInvalidStateException;
use App\Models\DataSubjectRequest;
use App\Models\User;
use App\Privacy\Contracts\DataSubjectRequestExecutionPolicy;
use App\Privacy\DataSubjectRequestStatus;
use App\Privacy\DataSubjectRequestType;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use App\Support\Authorization\ScopeReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class BeginDataExportRequestAction
{
    public function __construct(
        private DataSubjectRequestExecutionPolicy $executionPolicy,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    /** @param list<string> $dataCategories */
    public function handle(
        DataSubjectRequest $request,
        array $dataCategories,
        User $actor,
        ?ScopeReference $scope = null,
    ): DataSubjectRequest {
        $dataCategories = $this->validatedCategories($dataCategories);

        return DB::transaction(function () use ($request, $dataCategories, $actor, $scope): DataSubjectRequest {
            $locked = DataSubjectRequest::query()->lockForUpdate()->findOrFail($request->getKey());

            if ($locked->request_type !== DataSubjectRequestType::Export) {
                throw new DataExportInvalidStateException('Only export requests may enter export processing.');
            }

            if ($locked->status !== DataSubjectRequestStatus::PendingReview) {
                throw new DataExportInvalidStateException('Only pending export requests may begin processing.');
            }

            $locked->forceFill([
                'scope_type' => $scope?->type,
                'scope_key' => $scope?->key,
                'data_categories' => $dataCategories,
            ]);
            $decision = $this->executionPolicy->decide($locked, $actor);

            if (! $decision->allowed) {
                throw new DataExportExecutionDeniedException($decision->reasonCode);
            }

            $locked->forceFill([
                'status' => DataSubjectRequestStatus::Processing,
                'reviewed_by_user_id' => $actor->getKey(),
                'reviewed_at' => now()->utc(),
                'decision_reason_code' => $decision->reasonCode,
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'privacy.data_export.processing_started',
                actor: $actor,
                targetType: 'data_subject_request',
                targetId: $locked->public_id,
                metadata: [
                    'data_category_count' => count($dataCategories),
                    'scope_type' => $scope?->type,
                    'status' => DataSubjectRequestStatus::Processing->value,
                ],
            ));

            return $locked;
        }, attempts: 3);
    }

    /** @param list<string> $categories
     * @return list<string>
     */
    private function validatedCategories(array $categories): array
    {
        $categories = array_values(array_unique($categories));

        if ($categories === [] || count($categories) > 50) {
            throw new InvalidArgumentException('Data exports require between 1 and 50 data categories.');
        }

        foreach ($categories as $category) {
            if (
                ! is_string($category)
                ||
                Str::length($category) > 100
                || ! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $category)
            ) {
                throw new InvalidArgumentException('Data export categories must be stable lowercase identifiers.');
            }
        }

        sort($categories);

        return $categories;
    }
}
