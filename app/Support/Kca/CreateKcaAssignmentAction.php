<?php

namespace App\Support\Kca;

use App\Kca\KcaAssignmentState;
use App\Models\KcaAssignment;
use App\Models\KcaEnrollment;
use App\Models\KcaModule;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateKcaAssignmentAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param  list<int>  $soulTreeLevels
     */
    public function handle(
        KcaEnrollment $enrollment,
        KcaModule $module,
        string $title,
        User $actor,
        string $kind = 'standard',
        array $soulTreeLevels = [],
        ?CarbonImmutable $dueAt = null,
        KcaAssignmentState $state = KcaAssignmentState::Assigned,
    ): KcaAssignment {
        $normalizedTitle = Str::squish($title);
        if ($normalizedTitle === '' || Str::length($normalizedTitle) > 191) {
            throw new InvalidArgumentException('KCA assignment titles must contain between 1 and 191 characters.');
        }
        $kind = $kind === 'soul_winning' ? 'soul_winning' : 'standard';
        $levels = array_values(array_filter(array_map('intval', $soulTreeLevels), fn (int $n): bool => $n > 0));
        if ($kind === 'soul_winning' && $levels === []) {
            throw new InvalidArgumentException('Soul-winning assignments require a levels tree such as 3,2,4.');
        }

        return DB::transaction(function () use ($enrollment, $module, $normalizedTitle, $actor, $kind, $levels, $dueAt, $state): KcaAssignment {
            $now = now()->utc();
            $assignment = KcaAssignment::query()->create([
                'kca_enrollment_id' => $enrollment->getKey(),
                'kca_module_id' => $module->getKey(),
                'title' => $normalizedTitle,
                'assignment_kind' => $kind,
                'soul_tree_spec' => $kind === 'soul_winning' ? ['levels' => $levels] : null,
                'state' => $state,
                'due_at' => $dueAt,
                'assigned_at' => $state === KcaAssignmentState::Draft ? null : $now,
                'last_transitioned_by_user_id' => $actor->getKey(),
            ]);

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.assignment.created',
                actor: $actor,
                targetType: 'kca_assignment',
                targetId: $assignment->public_id,
                metadata: [
                    'kind' => $kind,
                    'levels' => $levels,
                ],
            ));

            return $assignment;
        }, attempts: 3);
    }
}
