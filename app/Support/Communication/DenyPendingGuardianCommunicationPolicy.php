<?php

namespace App\Support\Communication;

use App\Communication\CommunicationChannel;
use App\Models\Person;
use App\Support\Communication\Contracts\GuardianCommunicationPolicy;

class DenyPendingGuardianCommunicationPolicy implements GuardianCommunicationPolicy
{
    public function decide(Person $person, CommunicationChannel $channel): GuardianCommunicationDecision
    {
        return GuardianCommunicationDecision::deny('guardian_policy_pending');
    }
}
