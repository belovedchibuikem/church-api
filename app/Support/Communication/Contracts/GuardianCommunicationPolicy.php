<?php

namespace App\Support\Communication\Contracts;

use App\Communication\CommunicationChannel;
use App\Models\Person;
use App\Support\Communication\DenyPendingGuardianCommunicationPolicy;
use App\Support\Communication\GuardianCommunicationDecision;
use Illuminate\Container\Attributes\Bind;

#[Bind(DenyPendingGuardianCommunicationPolicy::class)]
interface GuardianCommunicationPolicy
{
    public function decide(Person $person, CommunicationChannel $channel): GuardianCommunicationDecision;
}
