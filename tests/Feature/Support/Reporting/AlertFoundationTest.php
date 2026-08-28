<?php

namespace Tests\Feature\Support\Reporting;

use App\Models\AlertOccurrence;
use App\Models\AlertRule;
use App\Models\User;
use App\Reporting\Actions\CreateAlertRuleAction;
use App\Reporting\Actions\EvaluateAlertRuleAction;
use App\Reporting\Actions\ResolveAlertOccurrenceAction;
use App\Reporting\AlertEvaluationContext;
use App\Reporting\AlertEvaluationDecision;
use App\Reporting\AlertSeverity;
use App\Reporting\Contracts\AlertRuleEvaluationPolicy;
use App\Reporting\Contracts\AlertVisibilityPolicy;
use App\Reporting\Queries\VisibleAlertOccurrencesQuery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AlertFoundationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_created_rules_are_inactive_audited_and_hide_encrypted_configuration(): void
    {
        $rule = $this->app->make(CreateAlertRuleAction::class)->handle(
            'alerts.first_timer_uncontacted',
            'First timer has not been contacted',
            'first_timer.uncontacted',
            AlertSeverity::Warning,
            ['elapsed_hours' => 48],
            User::factory()->create(),
        );

        $this->assertFalse($rule->is_active);
        $this->assertArrayNotHasKey('configuration', $rule->toArray());
        $this->assertNotSame(
            json_encode(['elapsed_hours' => 48]),
            AlertRule::query()->whereKey($rule)->value('configuration'),
        );
        $this->assertDatabaseHas('audit_events', ['action' => 'reporting.alert_rule.created']);
    }

    public function test_default_evaluation_policy_returns_null_when_condition_does_not_match(): void
    {
        $rule = AlertRule::factory()->active()->create();

        $occurrence = $this->app->make(EvaluateAlertRuleAction::class)->handle(
            $rule,
            new AlertEvaluationContext('first_timer', '01TESTREFERENCE'),
        );

        $this->assertNull($occurrence);
        $this->assertSame(0, AlertOccurrence::query()->count());
    }

    public function test_unresolved_conditions_deduplicate_and_resolution_allows_a_future_occurrence(): void
    {
        $this->app->instance(AlertRuleEvaluationPolicy::class, new class implements AlertRuleEvaluationPolicy
        {
            public function decide(AlertRule $rule, AlertEvaluationContext $context): AlertEvaluationDecision
            {
                return AlertEvaluationDecision::matched();
            }
        });
        $this->app->instance(AlertVisibilityPolicy::class, new class implements AlertVisibilityPolicy
        {
            public function allows(User $user, AlertOccurrence $occurrence): bool
            {
                return true;
            }
        });
        $actor = User::factory()->create();
        $rule = AlertRule::factory()->active()->create();
        $context = new AlertEvaluationContext('first_timer', '01STABLECONDITION', summary: 'Follow-up is overdue.');
        $action = $this->app->make(EvaluateAlertRuleAction::class);

        $first = $action->handle($rule, $context, $actor);
        $retry = $action->handle($rule, $context, $actor);

        $this->assertNotNull($first);
        $this->assertTrue($first->is($retry));
        $this->assertSame(1, AlertOccurrence::query()->count());

        $this->app->make(ResolveAlertOccurrenceAction::class)->handle($first, 'condition_cleared', $actor);
        $reopened = $action->handle($rule, $context, $actor);

        $this->assertFalse($first->is($reopened));
        $this->assertSame(2, AlertOccurrence::query()->count());
    }

    public function test_default_visibility_policy_returns_authenticated_alerts(): void
    {
        $occurrence = AlertOccurrence::factory()->create();

        $visible = $this->app->make(VisibleAlertOccurrencesQuery::class)
            ->resolve(User::factory()->create());

        $this->assertCount(1, $visible);
        $this->assertTrue($visible->first()->is($occurrence));
    }
}
