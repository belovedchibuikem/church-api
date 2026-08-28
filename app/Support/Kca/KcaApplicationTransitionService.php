<?php

namespace App\Support\Kca;

use App\Exceptions\KcaInvalidTransitionException;
use App\Kca\KcaApplicationState;

class KcaApplicationTransitionService
{
    public function assertCanTransition(KcaApplicationState $from, KcaApplicationState $to): void
    {
        $allowed = match ($from) {
            KcaApplicationState::Received => [KcaApplicationState::Reviewed],
            KcaApplicationState::Reviewed => [
                KcaApplicationState::Accepted,
                KcaApplicationState::ProvisionallyAccepted,
                KcaApplicationState::Deferred,
                KcaApplicationState::NotAccepted,
            ],
            KcaApplicationState::Accepted,
            KcaApplicationState::ProvisionallyAccepted,
            KcaApplicationState::Deferred,
            KcaApplicationState::NotAccepted => [],
        };

        if (! in_array($to, $allowed, true)) {
            throw new KcaInvalidTransitionException('kca_application', $from->value, $to->value);
        }
    }
}
