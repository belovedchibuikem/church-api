<?php

namespace App\Reporting;

use App\Models\AlertRule;
use App\Reporting\Contracts\AlertRuleEvaluationPolicy;

class PendingAlertRuleEvaluationPolicy implements AlertRuleEvaluationPolicy
{
    public function decide(AlertRule $rule, AlertEvaluationContext $context): AlertEvaluationDecision
    {
        return AlertEvaluationDecision::denied();
    }
}
