<?php

namespace App\Support\Health;

interface ReadinessChecker
{
    public function check(): ReadinessResult;
}
