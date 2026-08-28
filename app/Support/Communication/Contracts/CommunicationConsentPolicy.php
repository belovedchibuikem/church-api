<?php

namespace App\Support\Communication\Contracts;

use App\Communication\CommunicationChannel;
use App\Models\Person;
use App\Support\Communication\CommunicationConsentDecision;
use App\Support\Communication\CommunicationPurpose;
use App\Support\Communication\DatabaseCommunicationConsentPolicy;
use Illuminate\Container\Attributes\Bind;

#[Bind(DatabaseCommunicationConsentPolicy::class)]
interface CommunicationConsentPolicy
{
    public function decide(
        Person $person,
        CommunicationChannel $channel,
        CommunicationPurpose $purpose,
    ): CommunicationConsentDecision;
}
