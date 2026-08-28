<?php

namespace Tests\Feature\Support\Communication;

use App\Communication\CommunicationAudienceRuleType;
use App\Communication\CommunicationChannel;
use App\Communication\CommunicationRecipientStatus;
use App\Models\CommunicationAudience;
use App\Models\CommunicationAudienceRule;
use App\Models\CommunicationBroadcast;
use App\Models\Person;
use App\Models\PersonConsent;
use App\Models\PersonPreference;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Support\Communication\Contracts\GuardianCommunicationPolicy;
use App\Support\Communication\GuardianCommunicationDecision;
use App\Support\Communication\Queries\CommunicationAudienceCandidateQuery;
use App\Support\Communication\ResolveCommunicationAudienceAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResolveCommunicationAudienceActionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_resolves_server_side_candidates_and_suppresses_missing_consent(): void
    {
        $actor = User::factory()->create();
        $eligibleUser = User::factory()->withPerson()->create();
        $withoutConsent = User::factory()->withPerson()->create();
        $suspended = User::factory()->withPerson()->suspended()->create();
        $audience = CommunicationAudience::factory()->create();
        CommunicationAudienceRule::factory()->create([
            'communication_audience_id' => $audience->getKey(),
            'type' => CommunicationAudienceRuleType::AllUsers,
            'selector_key' => null,
            'scope_type' => null,
            'scope_key' => null,
        ]);
        PersonConsent::factory()->create([
            'person_id' => $eligibleUser->person_id,
            'purpose' => 'communications.updates',
        ]);
        PersonPreference::factory()->create([
            'person_id' => $eligibleUser->person_id,
            'notification_channels' => ['email'],
        ]);
        $broadcast = CommunicationBroadcast::factory()->create([
            'communication_audience_id' => $audience->getKey(),
            'channel' => CommunicationChannel::Email,
            'purpose' => 'communications.updates',
        ]);
        $this->app->instance(GuardianCommunicationPolicy::class, new class implements GuardianCommunicationPolicy
        {
            public function decide(Person $person, CommunicationChannel $channel): GuardianCommunicationDecision
            {
                return GuardianCommunicationDecision::allow();
            }
        });

        $resolved = $this->app->make(ResolveCommunicationAudienceAction::class)->handle($broadcast, $actor);

        $this->assertSame('prepared', $resolved->status->value);
        $this->assertDatabaseHas('communication_recipients', [
            'user_id' => $eligibleUser->getKey(),
            'status' => CommunicationRecipientStatus::Eligible->value,
            'reason_code' => 'eligible',
        ]);
        $this->assertDatabaseHas('communication_recipients', [
            'user_id' => $withoutConsent->getKey(),
            'status' => CommunicationRecipientStatus::Suppressed->value,
            'reason_code' => 'consent_missing_or_withdrawn',
        ]);
        $this->assertDatabaseMissing('communication_recipients', ['user_id' => $suspended->getKey()]);
    }

    public function test_default_guardian_policy_safely_suppresses_pending_policy(): void
    {
        $actor = User::factory()->create();
        $candidate = User::factory()->withPerson()->create();
        $audience = CommunicationAudience::factory()->create();
        CommunicationAudienceRule::factory()->create([
            'communication_audience_id' => $audience->getKey(),
            'type' => CommunicationAudienceRuleType::AllUsers,
            'selector_key' => null,
        ]);
        PersonConsent::factory()->create([
            'person_id' => $candidate->person_id,
            'purpose' => 'communications.updates',
        ]);
        PersonPreference::factory()->create([
            'person_id' => $candidate->person_id,
            'notification_channels' => ['email'],
        ]);
        $broadcast = CommunicationBroadcast::factory()->create([
            'communication_audience_id' => $audience->getKey(),
            'channel' => CommunicationChannel::Email,
            'purpose' => 'communications.updates',
        ]);

        $this->app->make(ResolveCommunicationAudienceAction::class)->handle($broadcast, $actor);

        $this->assertDatabaseHas('communication_recipients', [
            'user_id' => $candidate->getKey(),
            'status' => CommunicationRecipientStatus::Suppressed->value,
            'reason_code' => 'guardian_policy_pending',
        ]);
    }

    public function test_role_audience_excludes_assignments_that_have_not_started(): void
    {
        $role = Role::factory()->create(['code' => 'role.communication_recipient']);
        $activeUser = User::factory()->withPerson()->create();
        $scheduledUser = User::factory()->withPerson()->create();
        RoleAssignment::factory()->create([
            'role_id' => $role->getKey(),
            'user_id' => $activeUser->getKey(),
        ]);
        RoleAssignment::factory()->scheduled()->create([
            'role_id' => $role->getKey(),
            'user_id' => $scheduledUser->getKey(),
        ]);
        $audience = CommunicationAudience::factory()->create();
        CommunicationAudienceRule::factory()->create([
            'communication_audience_id' => $audience->getKey(),
            'type' => CommunicationAudienceRuleType::Role,
            'selector_key' => $role->code,
            'scope_type' => null,
            'scope_key' => null,
        ]);

        $candidates = $this->app->make(CommunicationAudienceCandidateQuery::class)->resolve($audience);

        $this->assertSame([$activeUser->getKey()], $candidates->modelKeys());
    }
}
