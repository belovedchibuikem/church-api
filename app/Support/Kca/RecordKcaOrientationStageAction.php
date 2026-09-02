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
        ->where('person_id', $person->getKey())
        ->latest('id')
        ->lockForUpdate()
        ->first();

      if ($application === null) {
        throw new AccessDeniedHttpException('No KCA application found.');
      }

      $status = $application->status instanceof KcaApplicationState
        ? $application->status
        : KcaApplicationState::from((string) $application->status);

      if ($status !== KcaApplicationState::Interview) {
        throw new ConflictHttpException('Orientation stages can only be recorded during interview / orientation.');
      }

      if ($application->orientation_completed_at !== null) {
        return $application;
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
