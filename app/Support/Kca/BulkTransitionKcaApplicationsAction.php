<?php

namespace App\Support\Kca;

use App\Kca\KcaApplicationState;
use App\Models\KcaApplication;
use App\Models\User;
use App\Support\Identity\PersonDisplayName;
use Throwable;

class BulkTransitionKcaApplicationsAction
{
    public function __construct(private TransitionKcaApplicationToStatusAction $transitionApplication) {}

    /**
     * @param  list<string>  $applicationIds
     * @return array{
     *   total: int,
     *   updated_count: int,
     *   skipped_count: int,
     *   failed_count: int,
     *   updated: list<array<string, mixed>>,
     *   failures: list<array{application_id: string, person_name?: string|null, error: string}>
     * }
     */
    public function handle(
        array $applicationIds,
        KcaApplicationState $status,
        User $actor,
        ?string $reasonCode = null,
    ): array {
        $updated = [];
        $failures = [];
        $skippedCount = 0;

        foreach (array_values(array_unique($applicationIds)) as $applicationId) {
            $application = KcaApplication::query()
                ->with(PersonDisplayName::eager())
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
                if ($application->status === $status) {
                    $skippedCount++;
                    $updated[] = [
                        'application_id' => $application->public_id,
                        'person_name' => $personName,
                        'status' => $status->value,
                        'skipped' => true,
                    ];

                    continue;
                }

                $application = $this->transitionApplication->handle(
                    $application,
                    $status,
                    $actor,
                    $reasonCode,
                );

                $updated[] = [
                    'application_id' => $application->public_id,
                    'person_name' => $personName,
                    'status' => $application->status instanceof KcaApplicationState
                        ? $application->status->value
                        : (string) $application->status,
                    'skipped' => false,
                ];
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
            'updated_count' => count(array_filter($updated, static fn (array $row): bool => ! ($row['skipped'] ?? false))),
            'skipped_count' => $skippedCount,
            'failed_count' => count($failures),
            'updated' => $updated,
            'failures' => $failures,
        ];
    }
}
