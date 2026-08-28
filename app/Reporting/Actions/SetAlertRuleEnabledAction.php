<?php

namespace App\Reporting\Actions;

use App\Models\AlertRule;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class SetAlertRuleEnabledAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(AlertRule $rule, bool $enabled, User $actor): AlertRule
    {
        return DB::transaction(function () use ($rule, $enabled, $actor): AlertRule {
            $locked = AlertRule::query()->lockForUpdate()->findOrFail($rule->getKey());

            if ($locked->is_active === $enabled) {
                return $locked;
            }

            $locked->forceFill([
                'is_active' => $enabled,
                'updated_by_user_id' => $actor->getKey(),
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: $enabled ? 'reporting.alert_rule.enabled' : 'reporting.alert_rule.disabled',
                actor: $actor,
                targetType: 'alert_rule',
                targetId: $locked->public_id,
                metadata: ['code' => $locked->code],
            ));

            return $locked;
        }, attempts: 3);
    }
}
