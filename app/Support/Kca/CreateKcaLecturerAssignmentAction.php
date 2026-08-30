<?php

namespace App\Support\Kca;

use App\Models\KcaCohort;
use App\Models\KcaLecturerAssignment;
use App\Models\KcaModule;
use App\Models\Person;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateKcaLecturerAssignmentAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(
        KcaModule $module,
        KcaCohort $cohort,
        Person $lecturer,
        CarbonImmutable $startsAt,
        ?CarbonImmutable $endsAt,
        User $actor,
    ): KcaLecturerAssignment {
        if ($endsAt !== null && $endsAt->lte($startsAt)) {
            throw new InvalidArgumentException('Lecturer assignment end must be after the start.');
        }

        return DB::transaction(function () use ($module, $cohort, $lecturer, $startsAt, $endsAt, $actor): KcaLecturerAssignment {
            $assignment = KcaLecturerAssignment::query()->create([
                'kca_module_id' => $module->getKey(),
                'kca_cohort_id' => $cohort->getKey(),
                'lecturer_person_id' => $lecturer->getKey(),
                'assigned_by_user_id' => $actor->getKey(),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.lecturer_assignment.created',
                actor: $actor,
                targetType: 'kca_lecturer_assignment',
                targetId: $assignment->public_id,
                metadata: [
                    'module_id' => $module->public_id,
                    'cohort_id' => $cohort->public_id,
                    'lecturer_person_id' => $lecturer->public_id,
                ],
            ));

            return $assignment;
        }, attempts: 3);
    }
}
