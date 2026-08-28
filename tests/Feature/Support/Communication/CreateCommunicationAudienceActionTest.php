<?php

namespace Tests\Feature\Support\Communication;

use App\Communication\CommunicationAudienceRuleType;
use App\Exceptions\CommunicationAudienceRuleException;
use App\Models\AuditEvent;
use App\Models\CommunicationAudience;
use App\Models\User;
use App\Support\Communication\CreateCommunicationAudienceAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CreateCommunicationAudienceActionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_creates_an_audited_server_side_audience_definition(): void
    {
        $actor = User::factory()->create();

        $audience = $this->app->make(CreateCommunicationAudienceAction::class)->handle(
            'audience.ministry_leaders',
            'Ministry leaders',
            [[
                'type' => CommunicationAudienceRuleType::Role,
                'selector_key' => 'role.ministry_leader',
            ]],
            $actor,
        );

        $this->assertDatabaseHas('communication_audiences', [
            'id' => $audience->getKey(),
            'code' => 'audience.ministry_leaders',
        ]);
        $this->assertDatabaseHas('communication_audience_rules', [
            'communication_audience_id' => $audience->getKey(),
            'type' => CommunicationAudienceRuleType::Role->value,
            'selector_key' => 'role.ministry_leader',
        ]);
        $this->assertSame('communications.audience.created', AuditEvent::query()->sole()->action);
    }

    public function test_it_rejects_a_client_supplied_selector_for_the_all_users_rule(): void
    {
        $this->expectException(CommunicationAudienceRuleException::class);

        $this->app->make(CreateCommunicationAudienceAction::class)->handle(
            'audience.unsafe',
            'Unsafe audience',
            [[
                'type' => CommunicationAudienceRuleType::AllUsers,
                'selector_key' => 'person-controlled-id',
            ]],
            User::factory()->make(),
        );

        $this->assertSame(0, CommunicationAudience::query()->count());
    }
}
