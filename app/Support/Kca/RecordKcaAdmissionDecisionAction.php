<?php

namespace App\Support\Kca;

use App\Kca\KcaApplicationState;
use App\Models\KcaAdmissionDecision;
use App\Models\KcaApplication;
use App\Models\User;
use InvalidArgumentException;

class RecordKcaAdmissionDecisionAction
{
    public function __construct(private TransitionKcaApplicationAction $transitionApplication) {}

    public function handle(
        KcaApplication $application,
        KcaApplicationState $outcome,
        User $actor,
        ?string $reasonCode = null,
    ): KcaAdmissionDecision {
        if (! $outcome->isAdmissionOutcome()) {
            throw new InvalidArgumentException('An admission decision requires a terminal application outcome.');
        }

        $transitionedApplication = $this->transitionApplication->handle(
            $application,
            $outcome,
            $actor,
            $reasonCode,
        );

        return $transitionedApplication->admissionDecision()->firstOrFail();
    }
}
