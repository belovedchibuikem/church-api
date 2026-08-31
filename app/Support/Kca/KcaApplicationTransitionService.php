<?php

namespace App\Support\Kca;

use App\Exceptions\KcaInvalidTransitionException;
use App\Kca\KcaApplicationState;

class KcaApplicationTransitionService
{
    public function assertCanTransition(KcaApplicationState $from, KcaApplicationState $to): void
    {
        $allowed = match ($from) {
            KcaApplicationState::Draft => [
                KcaApplicationState::Received,
                KcaApplicationState::Withdrawn,
            ],
            KcaApplicationState::Received => [
                KcaApplicationState::Reviewed,
                KcaApplicationState::InformationRequired,
                KcaApplicationState::Interview,
                KcaApplicationState::Withdrawn,
            ],
            KcaApplicationState::InformationRequired => [
                KcaApplicationState::Received,
                KcaApplicationState::Withdrawn,
            ],
            KcaApplicationState::Interview => [
                KcaApplicationState::Reviewed,
                KcaApplicationState::Withdrawn,
            ],
            KcaApplicationState::Reviewed => [
                KcaApplicationState::Accepted,
                KcaApplicationState::ProvisionallyAccepted,
                KcaApplicationState::Deferred,
                KcaApplicationState::NotAccepted,
                KcaApplicationState::InformationRequired,
            ],
            KcaApplicationState::ProvisionallyAccepted => [
                KcaApplicationState::Accepted,
                KcaApplicationState::Deferred,
                KcaApplicationState::NotAccepted,
            ],
            KcaApplicationState::Accepted => [
                KcaApplicationState::Suspended,
                KcaApplicationState::Revoked,
            ],
            KcaApplicationState::Deferred,
            KcaApplicationState::NotAccepted,
            KcaApplicationState::Withdrawn,
            KcaApplicationState::Suspended,
            KcaApplicationState::Revoked => [],
        };

        if (! in_array($to, $allowed, true)) {
            throw new KcaInvalidTransitionException('kca_application', $from->value, $to->value);
        }
    }
}
