<?php

namespace App\Support\Kca;

use App\Kca\KcaApplicationState;
use App\Kca\KcaOrientationStages;
use App\Models\KcaApplication;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CompleteKcaOrientationAction
{
  public function __construct(
    private RecordKcaOrientationStageAction $recordStage,
    private TransitionKcaApplicationAction $transition,
  ) {}

  public function handleForApplicant(Person $person, User $actor): KcaApplication
  {
    $application = KcaApplication::query()
      ->where('person_id', $person->getKey())
      ->latest('id')
      ->first();

    if ($application === null) {
      throw new AccessDeniedHttpException('No KCA application found.');
    }

    $status = $application->status instanceof KcaApplicationState
      ? $application->status
      : KcaApplicationState::from((string) $application->status);

    if ($status !== KcaApplicationState::Interview) {
      throw new ConflictHttpException('Orientation can only be completed during interview / orientation.');
    }

    foreach (KcaOrientationStages::all() as $stage) {
      $application = $this->recordStage->handle($person, $stage);
    }

    return $this->finalize($application, $actor);
  }

  public function handleForAdmin(KcaApplication $application, User $actor): KcaApplication
  {
    $status = $application->status instanceof KcaApplicationState
      ? $application->status
      : KcaApplicationState::from((string) $application->status);

    if ($status !== KcaApplicationState::Interview) {
      throw new ConflictHttpException('Orientation can only be completed during interview / orientation.');
    }

    return DB::transaction(function () use ($application, $actor): KcaApplication {
      $locked = KcaApplication::query()->lockForUpdate()->findOrFail($application->getKey());
      $locked->orientation_progress = KcaOrientationStages::all();
      $locked->save();

      return $this->finalize($locked, $actor);
    }, attempts: 3);
  }

  private function finalize(KcaApplication $application, User $actor): KcaApplication
  {
    $progress = collect($application->orientation_progress ?? [])
      ->filter(fn (mixed $value): bool => is_string($value) && KcaOrientationStages::isValid($value))
      ->unique()
      ->values()
      ->all();

    $missing = array_diff(KcaOrientationStages::all(), $progress);
    if ($missing !== []) {
      throw new ConflictHttpException('Complete all orientation stages before submitting.');
    }

    if ($application->orientation_completed_at !== null) {
      $status = $application->status instanceof KcaApplicationState
        ? $application->status
        : KcaApplicationState::from((string) $application->status);

      return $status === KcaApplicationState::Reviewed
        ? $application
        : $this->transition->handle($application, KcaApplicationState::Reviewed, $actor);
    }

    $application->orientation_completed_at = now()->utc();
    $application->save();

    return $this->transition->handle(
      $application,
      KcaApplicationState::Reviewed,
      $actor,
    );
  }
}
