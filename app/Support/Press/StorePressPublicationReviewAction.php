<?php

namespace App\Support\Press;

use App\Models\Person;
use App\Models\PressPublication;
use App\Models\PressPublicationReview;
use App\Models\User;
use App\Press\PressReviewDecision;
use App\Press\PressReviewStage;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class StorePressPublicationReviewAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param  array<string, mixed>|null  $checklist
     */
    public function handle(
        PressPublication $publication,
        User $actor,
        PressReviewStage $stage,
        PressReviewDecision $decision,
        ?Person $reviewer,
        ?string $comments,
        ?string $requestedChanges,
        ?array $checklist,
        bool $commentsPublic = false,
    ): PressPublicationReview {
        return DB::transaction(function () use (
            $publication,
            $actor,
            $stage,
            $decision,
            $reviewer,
            $comments,
            $requestedChanges,
            $checklist,
            $commentsPublic,
        ): PressPublicationReview {
            $locked = PressPublication::query()->lockForUpdate()->findOrFail($publication->getKey());

            $review = new PressPublicationReview;
            $review->forceFill([
                'press_publication_id' => $locked->getKey(),
                'reviewer_person_id' => $reviewer?->getKey(),
                'stage' => $stage,
                'decision' => $decision,
                'checklist' => $checklist,
                'comments' => $comments,
                'requested_changes' => $requestedChanges,
                'comments_public' => $commentsPublic,
                'decided_at' => now()->utc(),
            ]);
            $review->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'press.publication.reviewed',
                actor: $actor,
                targetType: 'press_publication',
                targetId: $locked->public_id,
                scopeType: 'press_publication',
                scopeId: $locked->public_id,
                metadata: [
                    'stage' => $stage->value,
                    'decision' => $decision->value,
                ],
            ));

            return $review;
        }, attempts: 3);
    }
}
