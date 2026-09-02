<?php

namespace App\Support\Kca;

use App\Exceptions\KcaIdempotencyConflictException;
use App\Exceptions\KcaInvalidTransitionException;
use App\Models\KcaAdmissionDecision;
use App\Models\KcaApplication;
use App\Models\KcaCohort;
use App\Models\KcaEnrollment;
use App\Models\KcaYear;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class EnrollKcaStudentAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private GenerateKcaRegistrationNumberAction $registrationNumbers,
    ) {}

    public function handle(
        KcaApplication $application,
        KcaCohort $cohort,
        ?string $registrationNumber,
        CarbonInterface $startsOn,
        User $actor,
    ): KcaEnrollment {
        $startsOn = $startsOn->toImmutable()->startOfDay();
        $requestedRegistrationNumber = trim((string) ($registrationNumber ?? ''));

        return DB::transaction(function () use (
            $application,
            $cohort,
            $requestedRegistrationNumber,
            $startsOn,
            $actor,
        ): KcaEnrollment {
            $lockedApplication = KcaApplication::query()->lockForUpdate()->findOrFail($application->getKey());
            $lockedCohort = KcaCohort::query()->lockForUpdate()->findOrFail($cohort->getKey());
            $lockedYear = KcaYear::query()->lockForUpdate()->findOrFail($lockedCohort->kca_year_id);
            $registrationNumber = $requestedRegistrationNumber;
            if ($registrationNumber === '') {
                $registrationNumber = $this->registrationNumbers->handle($lockedYear);
            }

            if ($registrationNumber === '' || Str::length($registrationNumber) > 100) {
                throw new InvalidArgumentException('KCA registration numbers must contain 1 to 100 characters.');
            }
            $decision = KcaAdmissionDecision::query()
                ->whereBelongsTo($lockedApplication, 'application')
                ->lockForUpdate()
                ->first();

            if (
                ! $lockedApplication->status->permitsEnrollment()
                || $decision === null
                || $decision->outcome !== $lockedApplication->status
            ) {
                throw new KcaInvalidTransitionException(
                    'kca_enrollment',
                    $lockedApplication->status->value,
                    'enrolled',
                );
            }

            $existing = KcaEnrollment::query()
                ->whereBelongsTo($lockedApplication, 'application')
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if (
                    $existing->kca_cohort_id !== $lockedCohort->getKey()
                    || $existing->kca_year_id !== $lockedYear->getKey()
                    || $existing->registration_number !== $registrationNumber
                    || ! $existing->starts_on->isSameDay($startsOn)
                ) {
                    throw new KcaIdempotencyConflictException;
                }

                return $existing;
            }

            $enrollment = (new KcaEnrollment)->forceFill([
                'kca_application_id' => $lockedApplication->getKey(),
                'person_id' => $lockedApplication->person_id,
                'kca_year_id' => $lockedYear->getKey(),
                'kca_cohort_id' => $lockedCohort->getKey(),
                'registration_number' => $registrationNumber,
                'starts_on' => $startsOn,
                'created_by_user_id' => $actor->getKey(),
            ]);
            $enrollment->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.enrollment.created',
                actor: $actor,
                targetType: 'kca_enrollment',
                targetId: $enrollment->public_id,
                scopeType: 'kca_cohort',
                scopeId: $lockedCohort->public_id,
                metadata: [
                    'application_id' => $lockedApplication->public_id,
                    'year_id' => $lockedYear->public_id,
                    'cohort_id' => $lockedCohort->public_id,
                ],
            ));

            return $enrollment;
        }, attempts: 3);
    }
}
