<?php

namespace App\Church\Contracts;

use App\Models\Church;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

interface FirstTimerFollowUpDuePolicy
{
    public function dueAt(Church $church, CarbonInterface $registeredAt): CarbonImmutable;
}
