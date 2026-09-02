<?php

namespace App\Support\Kca;

use App\Kca\KcaApplicationState;
use App\Models\KcaApplication;
use App\Models\User;

class TransitionKcaApplicationToStatusAction
{
  public function __construct(private TransitionKcaApplicationAction $transition) {}

  public function handle(
    KcaApplication $application,
    KcaApplicationState $target,
    User $actor,
    ?string $reasonCode = null,
  ): KcaApplication {
    foreach ($this->path($application->status, $target) as $step) {
      $application = $this->transition->handle(
        $application,
        $step,
        $actor,
        $step === $target ? $reasonCode : null,
      );
    }

    return $application;
  }

  /** @return list<KcaApplicationState> */
  private function path(KcaApplicationState $from, KcaApplicationState $to): array
  {
    if ($from === $to) {
      return [$to];
    }

    $terminalOutcomes = [
      KcaApplicationState::Accepted,
      KcaApplicationState::ProvisionallyAccepted,
      KcaApplicationState::Deferred,
      KcaApplicationState::NotAccepted,
    ];

    if (
      in_array($to, $terminalOutcomes, true)
      && in_array($from, [KcaApplicationState::Received, KcaApplicationState::Interview], true)
    ) {
      return [KcaApplicationState::Reviewed, $to];
    }

    return [$to];
  }
}
