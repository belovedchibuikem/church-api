<?php

namespace App\Support\Kca;

use App\Kca\KcaApplicationState;
use App\Kca\KcaOrientationStages;
use App\Models\KcaApplication;
use App\Models\Person;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RecordKcaOrientationStageAction
{
    public function handle(Person $person, string $stage): KcaApplication
    {
        if (! KcaOrientationStages::isValid($stage)) {
            throw new InvalidArgumentException('Unknown orientation stage.');
        }

        return DB::transaction(function () use ($person, $stage): KcaApplication {
            $application = KcaApplication::query()
                ->with('admissionLetter:id,kca_application_id,applicant_accepted_at')
                ->where('person_id', $person->getKey())
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($application === null) {
                throw new AccessDeniedHttpException('No KCA application found.');
            }

            if ($application->orientation_completed_at !== null) {
                return $application;
            }

            $status = $application->status instanceof KcaApplicationState
              ? $application->status
              : KcaApplicationState::from((string) $application->status);

            $canRecord = $status === KcaApplicationState::Interview
              || (
                  in_array($status, [KcaApplicationState::Accepted, KcaApplicationState::ProvisionallyAccepted], true)
                  && $application->admissionLetter !== null
                  && $application->admissionLetter->applicant_accepted_at !== null
              );

            if (! $canRecord) {
                throw new ConflictHttpException('Orientation stages can only be recorded during interview, or after accepting your admission letter.');
            }

            $progress = collect($application->orientation_progress ?? [])
                ->filter(fn (mixed $value): bool => is_string($value) && KcaOrientationStages::isValid($value))
                ->values()
                ->all();

            if (! in_array($stage, $progress, true)) {
                $progress[] = $stage;
                $application->orientation_progress = $progress;
                $application->save();
            }

            return $application->refresh();
        }, attempts: 3);
    }
}
