<?php

namespace App\Support\Press;

use App\Models\PressTranslation;
use App\Models\User;
use App\Press\PressTranslationStatus;
use App\Press\PressWorkflowReason;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use DomainException;
use Illuminate\Support\Facades\DB;

class TransitionPressTranslationAction
{
    public function __construct(
        private RecordPressTranslationTransitionAction $recordTransition,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        PressTranslation $translation,
        PressTranslationStatus $to,
        User $actor,
        string $reasonCode,
    ): PressTranslation {
        $reasonCode = PressWorkflowReason::validate($reasonCode);

        return DB::transaction(function () use ($translation, $to, $actor, $reasonCode): PressTranslation {
            $lockedTranslation = PressTranslation::query()->lockForUpdate()->findOrFail($translation->getKey());
            $from = $lockedTranslation->status;

            if ($from === $to) {
                return $lockedTranslation;
            }

            if (! $from->canTransitionTo($to)) {
                throw new DomainException("Translation cannot transition from {$from->value} to {$to->value}.");
            }

            $now = now()->utc();
            $lockedTranslation->status = $to;
            $lockedTranslation->status_changed_at = $now;

            if ($to === PressTranslationStatus::Reviewed) {
                $lockedTranslation->reviewed_at = $now;
            }

            if ($to === PressTranslationStatus::Approved) {
                $lockedTranslation->approved_at = $now;
            }

            $lockedTranslation->save();
            $this->recordTransition->handle($lockedTranslation, $actor, $from, $to, $reasonCode);

            $publicationPublicId = $lockedTranslation->publication()->value('public_id');
            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'press.translation.transitioned',
                actor: $actor,
                targetType: 'press_translation',
                targetId: $lockedTranslation->public_id,
                scopeType: 'press_publication',
                scopeId: (string) $publicationPublicId,
                metadata: [
                    'from' => $from->value,
                    'to' => $to->value,
                    'target_language_code' => $lockedTranslation->target_language_code,
                    'reason_code' => $reasonCode,
                ],
            ));

            return $lockedTranslation;
        }, attempts: 3);
    }
}
