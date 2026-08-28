<?php

namespace App\Reporting\Actions;

use App\Exceptions\AlertExecutionDeniedException;
use App\Models\AlertOccurrence;
use App\Models\AlertRule;
use App\Models\User;
use App\Reporting\AlertEvaluationContext;
use App\Reporting\AlertOccurrenceStatus;
use App\Reporting\Contracts\AlertRuleEvaluationPolicy;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EvaluateAlertRuleAction
{
    public function __construct(
        private AlertRuleEvaluationPolicy $evaluationPolicy,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        AlertRule $rule,
        AlertEvaluationContext $context,
        ?User $actor = null,
    ): ?AlertOccurrence {
        return DB::transaction(function () use ($rule, $context, $actor): ?AlertOccurrence {
            $lockedRule = AlertRule::query()->lockForUpdate()->findOrFail($rule->getKey());

            if (! $lockedRule->is_active) {
                throw new AlertExecutionDeniedException('alert_rule_inactive');
            }

            if (
                $lockedRule->scope_type !== $context->scope?->type
                || $lockedRule->scope_key !== $context->scope?->key
            ) {
                throw new AlertExecutionDeniedException('alert_scope_mismatch');
            }

            $decision = $this->evaluationPolicy->decide($lockedRule, $context);

            if (! $decision->allowed) {
                throw new AlertExecutionDeniedException($decision->reasonCode);
            }

            if (! $decision->matched) {
                return null;
            }

            $fingerprint = hash_hmac(
                'sha256',
                $context->conditionReferenceType."\0".$context->conditionReferenceKey,
                $this->hashKey(),
            );
            $existing = AlertOccurrence::query()
                ->whereBelongsTo($lockedRule, 'rule')
                ->where('condition_fingerprint_hash', $fingerprint)
                ->where('active_marker', 1)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $occurrence = (new AlertOccurrence)->forceFill([
                'alert_rule_id' => $lockedRule->getKey(),
                'condition_reference_type' => $context->conditionReferenceType,
                'condition_reference_key' => $context->conditionReferenceKey,
                'condition_fingerprint_hash' => $fingerprint,
                'scope_type' => $context->scope?->type,
                'scope_key' => $context->scope?->key,
                'status' => AlertOccurrenceStatus::Open,
                'active_marker' => 1,
                'summary' => $context->summary,
                'opened_at' => now()->utc(),
            ]);
            $occurrence->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'reporting.alert_occurrence.opened',
                actor: $actor,
                targetType: 'alert_occurrence',
                targetId: $occurrence->public_id,
                metadata: [
                    'rule_code' => $lockedRule->code,
                    'severity' => $lockedRule->severity->value,
                    'decision_reason' => $decision->reasonCode,
                    'scope_type' => $context->scope?->type,
                ],
            ));

            return $occurrence;
        }, attempts: 3);
    }

    private function hashKey(): string
    {
        $key = config('app.key');

        if (! is_string($key) || $key === '') {
            throw new InvalidArgumentException('The application key is required for alert fingerprints.');
        }

        return $key;
    }
}
