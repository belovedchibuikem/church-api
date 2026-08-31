<?php

namespace Tests\Feature;

use App\Mission\CrusadeStatus;
use App\Mission\MissionInvitationStatus;
use App\Models\AdministrativeUnit;
use App\Models\AuditEvent;
use App\Models\Church;
use App\Models\Crusade;
use App\Models\Location;
use App\Models\MissionInvitation;
use App\Models\MissionSoulJourney;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\GrantPermissionToRoleAction;
use App\Support\Authorization\ScopeReference;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MissionCrusadeLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_creates_crusade_and_rejects_invalid_then_accepts_valid_transitions(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['mission.crusades.view', 'mission.crusades.manage'], $scope);
        $this->authenticate($actor);
        $headers = $this->headers($scope);

        $id = $this->withHeaders($headers)->postJson('/api/v1/admin/mission/crusades', [
            'name' => 'Accra Harvest Crusade',
            'theme' => 'Jesus Saves',
            'purpose' => 'City-wide evangelism',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->json('data.id');

        $this->withHeaders($headers)->getJson("/api/v1/admin/mission/crusades/{$id}")
            ->assertOk()
            ->assertJsonPath('data.allowed_transitions.0', 'submitted');

        $this->withHeaders($headers)->postJson("/api/v1/admin/mission/crusades/{$id}/transitions", [
            'status' => 'active',
        ])->assertStatus(422);

        $this->withHeaders($headers)->postJson("/api/v1/admin/mission/crusades/{$id}/transitions", [
            'status' => 'submitted',
        ])->assertOk()->assertJsonPath('data.status', 'submitted');

        $this->withHeaders($headers)->postJson("/api/v1/admin/mission/crusades/{$id}/archive", [
            'reason_code' => 'closed_complete',
        ])->assertStatus(422);

        $this->assertTrue(AuditEvent::query()->where('action', 'mission.crusade.created')->where('target_id', $id)->exists());
        $this->assertTrue(AuditEvent::query()->where('action', 'mission.crusade.transitioned')->where('target_id', $id)->exists());
    }

    public function test_capture_is_not_souls_won_until_conversion_event(): void
    {
        $unit = AdministrativeUnit::factory()->create();
        $location = Location::factory()->create([
            'country_id' => $unit->country_id,
            'administrative_unit_id' => $unit->getKey(),
        ]);
        $crusade = Crusade::factory()->for($location)->create(['status' => CrusadeStatus::Active]);
        $scope = new ScopeReference('administrative_unit', $unit->public_id);
        $actor = $this->actorWithPermissions([
            'mission.souls.capture', 'mission.souls.view', 'mission.follow_up.complete', 'mission.crusades.view',
        ], $scope);
        $this->authenticate($actor);
        $headers = $this->headers($scope);

        $soulId = $this->withHeaders([...$headers, 'Idempotency-Key' => 'capture-metric-0001'])
            ->postJson("/api/v1/admin/mission/crusades/{$crusade->public_id}/souls", [
                'given_name' => 'Kwame', 'family_name' => 'Mensah',
            ])->assertCreated()->assertJsonPath('data.status', 'new')->json('data.id');

        $this->withHeaders($headers)->getJson("/api/v1/admin/mission/souls/{$soulId}")
            ->assertOk()
            ->assertJsonPath('data.converted_at', null);

        $this->withHeaders($headers)->getJson('/api/v1/admin/dashboards/mission')
            ->assertOk()
            ->assertJsonPath('data.metrics.1.label', 'Souls Captured')
            ->assertJsonPath('data.metrics.2.label', 'Souls Won');

        $this->withHeaders($headers)->postJson("/api/v1/admin/mission/souls/{$soulId}/conversion", [
            'reason_code' => 'altar_call_recorded',
        ])->assertOk()->assertJsonPath('data.conversion_reason_code', 'altar_call_recorded');

        $this->assertNotNull(MissionSoulJourney::query()->where('public_id', $soulId)->value('converted_at'));
    }

    public function test_member_invitation_draft_and_admin_decline_require_reason(): void
    {
        $user = User::factory()->withPerson()->create();
        $this->authenticateMember($user);

        $invitationId = $this->postJson('/api/v1/user/mission/invitations', [
            'title' => 'Invite us to Kumasi',
            'type' => 'Crusade',
            'location' => 'Kumasi, Ghana',
            'details' => 'We can host at the stadium.',
            'idempotency_key' => 'member-invite-0001',
        ], ['Idempotency-Key' => 'member-invite-0001'])->assertCreated()
            ->assertJsonPath('data.status', 'received')
            ->json('data.id');

        $this->postJson('/api/v1/user/mission/invitations', [
            'title' => 'Invite us to Kumasi',
            'details' => 'We can host at the stadium.',
            'idempotency_key' => 'member-invite-0001',
        ])->assertCreated()->assertJsonPath('data.id', $invitationId);

        $this->getJson('/api/v1/user/mission/invitations')->assertOk()->assertJsonPath('meta.pagination.total', 1);

        $member = User::factory()->withPerson()->create();
        $this->authenticateMember($member);
        $this->getJson("/api/v1/user/mission/invitations/{$invitationId}")->assertNotFound();

        $scope = new ScopeReference('global', 'platform');
        $admin = $this->actorWithPermissions(['mission.invitations.transition', 'mission.invitations.manage'], $scope);
        $this->authenticate($admin);

        $this->withHeaders($this->headers($scope))->postJson("/api/v1/admin/mission/invitations/{$invitationId}/transitions", [
            'status' => 'declined',
        ])->assertStatus(422);

        $this->withHeaders($this->headers($scope))->postJson("/api/v1/admin/mission/invitations/{$invitationId}/transitions", [
            'status' => 'declined',
            'reason_code' => 'incomplete_safeguarding',
        ])->assertOk()->assertJsonPath('data.status', 'declined');
    }

    public function test_approving_member_invitation_creates_one_planning_crusade(): void
    {
        $user = User::factory()->withPerson()->create();
        $this->authenticateMember($user);
        $invitationId = $this->postJson('/api/v1/user/mission/invitations', [
            'title' => 'Tamale Outreach',
            'details' => 'Local church can host.',
            'idempotency_key' => 'member-invite-approve-1',
        ])->assertCreated()->json('data.id');

        $invitation = MissionInvitation::query()->where('public_id', $invitationId)->firstOrFail();
        $invitation->status = MissionInvitationStatus::UnderReview;
        $invitation->save();

        $scope = new ScopeReference('global', 'platform');
        $admin = $this->actorWithPermissions(['mission.invitations.transition'], $scope);
        $this->authenticate($admin);

        $this->withHeaders($this->headers($scope))->postJson("/api/v1/admin/mission/invitations/{$invitationId}/transitions", [
            'status' => 'approved',
        ])->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $fresh = $invitation->fresh();
        $this->assertNotNull($fresh->crusade_id);
        $this->assertSame(1, Crusade::query()->where('name', 'Tamale Outreach')->count());
        $this->assertSame(CrusadeStatus::Approved, $fresh->crusade->status);
    }

    public function test_member_cannot_read_souls_and_partners_can_be_created(): void
    {
        $user = User::factory()->withPerson()->create();
        $this->authenticate($user);
        $this->withHeaders($this->headers(new ScopeReference('global', 'platform')))
            ->getJson('/api/v1/admin/mission/souls')
            ->assertForbidden();

        $scope = new ScopeReference('global', 'platform');
        $admin = $this->actorWithPermissions(['mission.crusades.view', 'mission.crusades.manage'], $scope);
        $this->authenticate($admin);
        $this->withHeaders($this->headers($scope))->postJson('/api/v1/admin/mission/partners', [
            'name' => 'Bible Society',
            'partner_type' => 'organisation',
            'geography' => 'Ghana',
        ])->assertCreated()->assertJsonPath('data.name', 'Bible Society');

        $this->withHeaders($this->headers($scope))->getJson('/api/v1/admin/mission/follow-up/gaps')
            ->assertForbidden();

        $ops = $this->actorWithPermissions(['mission.follow_up.record'], $scope);
        $this->authenticate($ops);
        $this->withHeaders($this->headers($scope))->getJson('/api/v1/admin/mission/follow-up/gaps')
            ->assertOk()
            ->assertJsonStructure(['data' => ['unassigned', 'never_contacted', 'overdue', 'stalled', 'active_follow_ups']]);
    }

    public function test_connect_church_and_support_request(): void
    {
        $church = Church::factory()->create();
        $crusade = Crusade::factory()->create();
        $journey = MissionSoulJourney::factory()->for($crusade)->create();
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['mission.mentors.assign', 'mission.crusades.manage', 'mission.crusades.view'], $scope);
        $this->authenticate($actor);
        $headers = $this->headers($scope);

        $this->withHeaders($headers)->postJson("/api/v1/admin/mission/souls/{$journey->public_id}/church-connection", [
            'church_id' => $church->public_id,
        ])->assertOk()->assertJsonPath('data.connected_church_id', $church->public_id);

        $this->withHeaders($headers)->postJson('/api/v1/admin/mission/support-requests', [
            'title' => 'Follow-up materials',
            'category' => 'logistics',
        ])->assertCreated()->assertJsonPath('data.status', 'submitted');
    }

    /** @param array<int, string> $permissionCodes */
    private function actorWithPermissions(array $permissionCodes, ScopeReference $scope): User
    {
        $actor = User::factory()->create();
        $role = Role::factory()->create();
        foreach ($permissionCodes as $permissionCode) {
            $permission = Permission::factory()->create(['code' => $permissionCode]);
            $this->app->make(GrantPermissionToRoleAction::class)->handle($role, $permission);
        }
        $assignment = $this->app->make(AssignRoleToUserAction::class)->handle($actor, $role);
        $this->app->make(AssignScopeToRoleAssignmentAction::class)->handle($assignment, $scope);

        return $actor;
    }

    private function authenticate(User $user): void
    {
        $securitySession = SecuritySession::factory()->for($user)->create();
        $this->actingAs($user);
        $this->withSession([
            'security_session_id' => $securitySession->public_id,
            'auth.mfa_verified_at' => now()->utc()->toIso8601String(),
        ]);
    }

    private function authenticateMember(User $user): void
    {
        $securitySession = SecuritySession::factory()->for($user)->create();
        $this->actingAs($user);
        $this->withSession(['security_session_id' => $securitySession->public_id]);
    }

    /** @return array<string, string> */
    private function headers(ScopeReference $scope): array
    {
        return ['X-Scope-Type' => $scope->type, 'X-Scope-ID' => $scope->key];
    }
}
