<?php

namespace App\Support\Kca;

use App\Kca\KcaAssignmentState;
use App\Models\KcaAssignment;
use App\Models\KcaLesson;
use App\Models\KcaModule;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UpdateKcaAssignmentAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param  list<int>|null  $soulTreeLevels
     */
    public function handle(
        KcaAssignment $assignment,
        User $actor,
        ?string $title = null,
        ?CarbonImmutable $dueAt = null,
        bool $clearDueAt = false,
        ?array $soulTreeLevels = null,
        ?KcaModule $module = null,
        ?KcaLesson $lesson = null,
    ): KcaAssignment {
        if ($assignment->state === KcaAssignmentState::FinalAssessment) {
            throw new InvalidArgumentException('Final-assessment assignments cannot be edited.');
        }

        $updates = [];
        $targetModule = $module ?? $assignment->module;
        $targetLesson = $lesson ?? $assignment->lesson;

        if ($module !== null) {
            $updates['kca_module_id'] = $module->getKey();
            $targetModule = $module;
        }
        if ($lesson !== null) {
            $updates['kca_lesson_id'] = $lesson->getKey();
            $targetLesson = $lesson;
        }
        if ($targetLesson !== null && $targetModule !== null
            && (int) $targetLesson->kca_module_id !== (int) $targetModule->getKey()) {
            throw new InvalidArgumentException('The selected lesson must belong to the selected module.');
        }

        if ($title !== null) {
            $normalizedTitle = Str::squish($title);
            if ($normalizedTitle === '' || Str::length($normalizedTitle) > 191) {
                throw new InvalidArgumentException('KCA assignment titles must contain between 1 and 191 characters.');
            }
            $updates['title'] = $normalizedTitle;
        }
        if ($clearDueAt) {
            $updates['due_at'] = null;
        } elseif ($dueAt !== null) {
            $updates['due_at'] = $dueAt;
        }
        if ($soulTreeLevels !== null) {
            if (! $assignment->isSoulWinning()) {
                throw new InvalidArgumentException('Soul tree levels only apply to soul-winning assignments.');
            }
            $levels = array_values(array_filter(array_map('intval', $soulTreeLevels), fn (int $n): bool => $n > 0));
            if ($levels === []) {
                throw new InvalidArgumentException('Soul-winning assignments require a levels tree such as 3,2,4.');
            }
            $updates['soul_tree_spec'] = ['levels' => $levels];
        }

        if ($updates === []) {
            return $assignment;
        }

        return DB::transaction(function () use ($assignment, $actor, $updates): KcaAssignment {
            $assignment->forceFill($updates)->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.assignment.updated',
                actor: $actor,
                targetType: 'kca_assignment',
                targetId: $assignment->public_id,
                metadata: array_keys($updates),
            ));

            return $assignment->refresh();
        }, attempts: 3);
    }
}
