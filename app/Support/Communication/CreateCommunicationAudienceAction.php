<?php

namespace App\Support\Communication;

use App\Communication\CommunicationAudienceRuleType;
use App\Exceptions\CommunicationAudienceRuleException;
use App\Models\CommunicationAudience;
use App\Models\CommunicationAudienceRule;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use App\Support\Authorization\ScopeReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateCommunicationAudienceAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param  list<array{type: CommunicationAudienceRuleType, selector_key?: string|null, scope?: ScopeReference|null}>  $rules
     */
    public function handle(string $code, string $name, array $rules, User $actor): CommunicationAudience
    {
        $this->validateAudience($code, $name, $rules);

        return DB::transaction(function () use ($code, $name, $rules, $actor): CommunicationAudience {
            if (CommunicationAudience::query()->where('code', $code)->lockForUpdate()->exists()) {
                throw new CommunicationAudienceRuleException('The communication audience code already exists.');
            }

            $audience = (new CommunicationAudience)->forceFill([
                'code' => $code,
                'name' => $name,
                'created_by_user_id' => $actor->getKey(),
            ]);
            $audience->save();

            foreach ($rules as $ruleData) {
                $scope = $ruleData['scope'] ?? null;
                (new CommunicationAudienceRule)->forceFill([
                    'communication_audience_id' => $audience->getKey(),
                    'type' => $ruleData['type'],
                    'selector_key' => $ruleData['selector_key'] ?? null,
                    'scope_type' => $scope?->type,
                    'scope_key' => $scope?->key,
                ])->save();
            }

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'communications.audience.created',
                actor: $actor,
                targetType: 'communication_audience',
                targetId: $audience->public_id,
                metadata: [
                    'code' => $code,
                    'rule_types' => array_map(
                        static fn (array $rule): string => $rule['type']->value,
                        $rules,
                    ),
                ],
            ));

            return $audience;
        }, attempts: 3);
    }

    /**
     * @param  list<array{type: CommunicationAudienceRuleType, selector_key?: string|null, scope?: ScopeReference|null}>  $rules
     */
    private function validateAudience(string $code, string $name, array $rules): void
    {
        if (
            Str::length($code) > 100
            || ! Str::isMatch('/\Aaudience\.[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $code)
        ) {
            throw new CommunicationAudienceRuleException('Audience codes must be stable namespaced identifiers.');
        }

        if (trim($name) === '' || Str::length($name) > 191) {
            throw new CommunicationAudienceRuleException('Audience names must contain 1 to 191 characters.');
        }

        if ($rules === [] || count($rules) > 50) {
            throw new CommunicationAudienceRuleException('Audiences require between 1 and 50 server-side rules.');
        }

        foreach ($rules as $rule) {
            $this->validateRule($rule);
        }
    }

    /**
     * @param  array{type: CommunicationAudienceRuleType, selector_key?: string|null, scope?: ScopeReference|null}  $rule
     */
    private function validateRule(array $rule): void
    {
        $type = $rule['type'];
        $selectorKey = $rule['selector_key'] ?? null;
        $scope = $rule['scope'] ?? null;

        if ($type === CommunicationAudienceRuleType::AllUsers) {
            if ($selectorKey !== null || $scope !== null) {
                throw new CommunicationAudienceRuleException('All-user rules cannot contain a selector or scope.');
            }

            return;
        }

        if ($type === CommunicationAudienceRuleType::Scope) {
            if (! $scope instanceof ScopeReference || $selectorKey !== null) {
                throw new CommunicationAudienceRuleException('Scope rules require one exact scope reference.');
            }

            return;
        }

        if (
            ! is_string($selectorKey)
            || Str::length($selectorKey) > 191
            || ! Str::isMatch('/\A[^\s\x00-\x1F\x7F]+\z/u', $selectorKey)
            || $scope !== null
        ) {
            throw new CommunicationAudienceRuleException('Audience selectors require one opaque selector key.');
        }
    }
}
