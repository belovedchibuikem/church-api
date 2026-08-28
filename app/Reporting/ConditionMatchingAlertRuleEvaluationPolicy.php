<?php

namespace App\Reporting;

use App\Models\AlertRule;
use App\Reporting\Contracts\AlertRuleEvaluationPolicy;

class ConditionMatchingAlertRuleEvaluationPolicy implements AlertRuleEvaluationPolicy
{
    public function decide(AlertRule $rule, AlertEvaluationContext $context): AlertEvaluationDecision
    {
        if ($rule->condition_type === $context->conditionReferenceType) {
            return AlertEvaluationDecision::matched();
        }

        return AlertEvaluationDecision::noMatch();
    }
}
