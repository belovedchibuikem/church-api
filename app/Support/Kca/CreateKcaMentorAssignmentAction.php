<?php

namespace App\Support\Kca;

use App\Models\KcaEnrollment;
use App\Models\KcaMentorAssignment;
use App\Models\Person;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateKcaMentorAssignmentAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(
        KcaEnrollment $enrollment,
        Person $mentor,
        CarbonImmutable $startsAt,
        ?CarbonImmutable $endsAt,
        User $actor,
    ): KcaMentorAssignment {
        if ($endsAt !== null && $endsAt->lte($startsAt)) {
            throw new InvalidArgumentException('Mentor assignment end must be after the start.');
        }

        return DB::transaction(function () use ($enrollment, $mentor, $startsAt, $endsAt, $actor): KcaMentorAssignment {
            $activeExists = KcaMentorAssignment::query()
                ->where('kca_enrollment_id', $enrollment->getKey())
                ->whereNull('ends_at')
                ->lockForUpdate()
                ->exists();

            if ($activeExists) {
                throw new InvalidArgumentException('This enrollment already has an active mentor assignment.');
            }

            $assignment = KcaMentorAssignment::query()->create([
                'kca_enrollment_id' => $enrollment->getKey(),
                'mentor_person_id' => $mentor->getKey(),
                'assigned_by_user_id' => $actor->getKey(),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.mentor_assignment.created',
                actor: $actor,
                targetType: 'kca_mentor_assignment',
                targetId: $assignment->public_id,
                metadata: [
                    'enrollment_id' => $enrollment->public_id,
                    'mentor_person_id' => $mentor->public_id,
                ],
            ));

            return $assignment;
        }, attempts: 3);
    }
}
