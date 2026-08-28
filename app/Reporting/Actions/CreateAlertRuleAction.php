<?php

namespace App\Reporting\Actions;

use App\Models\AlertRule;
use App\Models\User;
use App\Reporting\AlertSeverity;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use App\Support\Authorization\ScopeReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;

class CreateAlertRuleAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param  array<string, bool|float|int|string|null>  $configuration
     *
     * @throws JsonException
     */
    public function handle(
        string $code,
        string $title,
        string $conditionType,
        AlertSeverity $severity,
        array $configuration,
        User $actor,
        ?ScopeReference $scope = null,
    ): AlertRule {
        $this->validate($code, $title, $conditionType, $configuration);

        return DB::transaction(function () use ($code, $title, $conditionType, $severity, $configuration, $actor, $scope): AlertRule {
            if (AlertRule::query()->where('code', $code)->lockForUpdate()->exists()) {
                throw new InvalidArgumentException('The alert rule code already exists.');
            }

            $rule = (new AlertRule)->forceFill([
                'code' => $code,
                'title' => $title,
                'condition_type' => $conditionType,
                'severity' => $severity,
                'scope_type' => $scope?->type,
                'scope_key' => $scope?->key,
                'configuration' => $configuration,
                'is_active' => false,
                'created_by_user_id' => $actor->getKey(),
                'updated_by_user_id' => $actor->getKey(),
            ]);
            $rule->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'reporting.alert_rule.created',
                actor: $actor,
                targetType: 'alert_rule',
                targetId: $rule->public_id,
                metadata: [
                    'code' => $code,
                    'condition_type' => $conditionType,
                    'severity' => $severity->value,
                    'scope_type' => $scope?->type,
                ],
            ));

            return $rule;
        }, attempts: 3);
    }

    /** @param array<string, bool|float|int|string|null> $configuration */
    private function validate(string $code, string $title, string $conditionType, array $configuration): void
    {
        if (
            Str::length($code) > 100
            || ! Str::isMatch('/\Aalerts\.[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $code)
        ) {
            throw new InvalidArgumentException('Alert rule codes must be stable namespaced identifiers.');
        }

        if (trim($title) === '' || Str::length($title) > 191) {
            throw new InvalidArgumentException('Alert rule titles must contain 1 to 191 characters.');
        }

        if (
            Str::length($conditionType) > 100
            || ! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $conditionType)
        ) {
            throw new InvalidArgumentException('Alert condition types must be stable lowercase identifiers.');
        }

        json_encode($configuration, JSON_THROW_ON_ERROR);
    }
}
