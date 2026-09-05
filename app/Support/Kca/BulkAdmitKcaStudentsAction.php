<?php

namespace App\Support\Kca;

use App\Kca\KcaApplicationState;
use App\Models\KcaApplication;
use App\Models\KcaCohort;
use App\Models\KcaEnrollment;
use App\Models\User;
use App\Support\Identity\PersonDisplayName;
use Carbon\CarbonInterface;
use Throwable;

class BulkAdmitKcaStudentsAction
{
    public function __construct(
        private TransitionKcaApplicationToStatusAction $transitionApplication,
        private EnrollKcaStudentAction $enrollStudent,
    ) {}

    /**
     * @param  list<string>  $applicationIds
     * @return array{
     *   total: int,
     *   admitted_count: int,
     *   skipped_count: int,
     *   failed_count: int,
     *   admitted: list<array<string, mixed>>,
     *   failures: list<array{application_id: string, person_name?: string|null, error: string}>
     * }
     */
    public function handle(
        array $applicationIds,
        KcaCohort $cohort,
        CarbonInterface $startsOn,
        User $actor,
        KcaApplicationState $outcome = KcaApplicationState::Accepted,
    ): array {
        if (! $outcome->permitsEnrollment()) {
            $outcome = KcaApplicationState::Accepted;
        }

        $admitted = [];
        $failures = [];
        $skippedCount = 0;
        $startsOn = $startsOn->toImmutable()->startOfDay();

        foreach (array_values(array_unique($applicationIds)) as $applicationId) {
            $application = KcaApplication::query()
                ->with([...PersonDisplayName::eager(), 'enrollment.cohort:id,public_id,name'])
                ->where('public_id', $applicationId)
                ->first();

            if ($application === null) {
                $failures[] = [
                    'application_id' => $applicationId,
                    'error' => 'Application not found.',
                ];

                continue;
            }

            $personName = PersonDisplayName::of($application->person) ?: null;

            try {
                $existing = $application->enrollment;
                if ($existing instanceof KcaEnrollment) {
                    if ($existing->kca_cohort_id !== $cohort->getKey()) {
                        $failures[] = [
                            'application_id' => $application->public_id,
                            'person_name' => $personName,
                            'error' => 'Student is already enrolled in a different cohort.',
                        ];

                        continue;
                    }

                    $skippedCount++;
                    $admitted[] = $this->row(
                        $application,
                        $existing,
                        $personName,
                        $cohort->public_id,
                        skipped: true,
                    );

                    continue;
                }

                $application = $this->prepareForEnrollment($application, $outcome, $actor);
                $enrollment = $this->enrollStudent->handle(
                    $application,
                    $cohort,
                    null,
                    $startsOn,
                    $actor,
                );

                $admitted[] = $this->row(
                    $application,
                    $enrollment,
                    $personName,
                    $cohort->public_id,
                    skipped: false,
                );
            } catch (Throwable $exception) {
                $failures[] = [
                    'application_id' => $application->public_id,
                    'person_name' => $personName,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return [
            'total' => count($applicationIds),
            'admitted_count' => count(array_filter($admitted, static fn (array $row): bool => ! ($row['skipped'] ?? false))),
            'skipped_count' => $skippedCount,
            'failed_count' => count($failures),
            'admitted' => $admitted,
            'failures' => $failures,
        ];
    }

    private function prepareForEnrollment(
        KcaApplication $application,
        KcaApplicationState $outcome,
        User $actor,
    ): KcaApplication {
        if ($application->status === $outcome && $application->status->permitsEnrollment()) {
            return $application;
        }

        return $this->transitionApplication->handle(
            $application,
            $outcome,
            $actor,
            $outcome->value.'_by_admin',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function row(
        KcaApplication $application,
        KcaEnrollment $enrollment,
        ?string $personName,
        string $cohortId,
        bool $skipped,
    ): array {
        return [
            'application_id' => $application->public_id,
            'person_name' => $personName,
            'status' => $application->status instanceof KcaApplicationState
                ? $application->status->value
                : (string) $application->status,
            'enrollment_id' => $enrollment->public_id,
            'registration_number' => $enrollment->registration_number,
            'cohort_id' => $enrollment->cohort?->public_id ?: $cohortId,
            'starts_on' => $enrollment->starts_on?->toDateString(),
            'skipped' => $skipped,
        ];
    }
}
