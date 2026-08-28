<?php

namespace App\Reporting\Contracts;

use App\Models\AlertRule;
use App\Reporting\AlertEvaluationContext;
use App\Reporting\AlertEvaluationDecision;
use App\Reporting\ConditionMatchingAlertRuleEvaluationPolicy;
use Illuminate\Container\Attributes\Bind;

#[Bind(ConditionMatchingAlertRuleEvaluationPolicy::class)]
interface AlertRuleEvaluationPolicy
{
    public function decide(AlertRule $rule, AlertEvaluationContext $context): AlertEvaluationDecision;
}
