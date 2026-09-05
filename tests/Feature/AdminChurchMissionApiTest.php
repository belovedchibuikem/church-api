<?php

namespace Tests\Feature;

use App\Church\FollowUpTaskStatus;
use App\Church\FollowUpTaskType;
use App\Church\HomeChurchApplicationStatus;
use App\Church\HomeChurchStatus;
use App\Models\AdministrativeUnit;
use App\Models\AuditEvent;
use App\Models\Church;
use App\Models\Crusade;
use App\Models\FirstTimer;
use App\Models\FollowUpTask;
use App\Models\HomeChurch;
use App\Models\HomeChurchApplication;
use App\Models\Location;
use App\Models\MissionTeamAssignment;
use App\Models\PastoralNeed;
use App\Models\Permission;
use App\Models\Person;
use App\Models\PrayerRequest;
use App\Models\Role;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\GrantPermissionToRoleAction;
use App\Support\Authorization\ScopeReference;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminChurchMissionApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_church_operator_can_create_church_and_progress_home_church_application(): void
    {
        $unit = AdministrativeUnit::factory()->create();
        $location = Location::factory()->create([
            'country_id' => $unit->country_id,
            'administrative_unit_id' => $unit->getKey(),
        ]);
        $scope = new ScopeReference('administrative_unit', $unit->public_id);
        $actor = $this->actorWithPermissions([
            'church.churches.manage',
            'church.churches.view',
            'church.home_church_applications.review',
        ], $scope);
        $this->authenticate($actor);
        $headers = $this->headers($scope);

        $churchId = $this->withHeaders($headers)->postJson('/api/v1/admin/church/churches', [
            'name' => 'Accra Family House',
            'location_id' => $location->public_id,
            'administrative_unit_id' => $unit->public_id,
        ])->assertCreated()->assertJsonPath('data.name', 'Accra Family House')->json('data.id');

        $church = Church::query()->where('public_id', $churchId)->firstOrFail();
        $application = HomeChurchApplication::factory()->for($church)->create();
        $application->status = HomeChurchApplicationStatus::Draft;
        $application->save();

        $this->withHeaders($headers)->postJson("/api/v1/admin/church/home-church-applications/{$application->public_id}/transitions", [
            'status' => 'submitted',
            'reason_code' => 'application_received',
        ])->assertOk()->assertJsonPath('data.status', 'submitted');

        $this->withHeaders($headers)->getJson("/api/v1/admin/church/churches/{$churchId}")
            ->assertOk()
            ->assertJsonPath('data.id', $churchId)
            ->assertJsonPath('data.home_churches_count', 0)
            ->assertJsonPath('data.applications_count', 1);

        $this->withHeaders($headers)->getJson('/api/v1/admin/church/churches')
            ->assertOk()->assertJsonPath('meta.pagination.total', 1);

        $this->assertTrue(AuditEvent::query()->where('action', 'church.created')->where('target_id', $churchId)->exists());
        $this->assertTrue(AuditEvent::query()->where('action', 'home_church.application.status_changed')->where('target_id', $application->public_id)->exists());

        $this->withHeaders($headers)
            ->getJson("/api/v1/admin/church/home-church-applications/{$application->public_id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.contact_phone', $application->contact_phone)
            ->assertJsonPath('data.contact_email', $application->contact_email)
            ->assertJsonPath('data.allowed_actions.0.status', 'under_review');

        $this->withHeaders($headers)->postJson("/api/v1/admin/church/home-church-applications/{$application->public_id}/transitions", [
            'status' => 'under_review',
            'reason_code' => 'review_started',
            'expected_status' => 'draft',
        ])->assertStatus(422);

        $this->withHeaders($headers)->postJson("/api/v1/admin/church/home-church-applications/{$application->public_id}/transitions", [
            'status' => 'rejected',
            'reason_code' => 'incomplete',
        ])->assertStatus(422);

        $this->withHeaders($headers)->postJson("/api/v1/admin/church/home-church-applications/{$application->public_id}/transitions", [
            'status' => 'rejected',
            'reason_code' => 'incomplete',
            'notes' => 'Missing leadership recommendation.',
            'expected_status' => 'submitted',
        ])->assertOk()->assertJsonPath('data.status', 'rejected');
    }

    public function test_unauthenticated_and_forbidden_home_church_application_access(): void
    {
        $application = HomeChurchApplication::factory()->create();

        $this->getJson("/api/v1/admin/church/home-church-applications/{$application->public_id}")
            ->assertUnauthorized();

        $unit = AdministrativeUnit::factory()->create();
        $scope = new ScopeReference('administrative_unit', $unit->public_id);
        $member = $this->actorWithPermissions(['church.churches.view'], $scope);
        $this->authenticate($member);

        $this->withHeaders($this->headers($scope))
            ->getJson("/api/v1/admin/church/home-church-applications/{$application->public_id}")
            ->assertForbidden();
    }

    public function test_home_church_show_and_status_change_require_reason(): void
    {
        $unit = AdministrativeUnit::factory()->create();
        $location = Location::factory()->create([
            'country_id' => $unit->country_id,
            'administrative_unit_id' => $unit->getKey(),
        ]);
        $scope = new ScopeReference('administrative_unit', $unit->public_id);
        $actor = $this->actorWithPermissions([
            'church.home_churches.view',
            'church.home_church_applications.manage',
        ], $scope);
        $this->authenticate($actor);
        $headers = $this->headers($scope);
        $church = Church::factory()->create([
            'location_id' => $location->getKey(),
            'administrative_unit_id' => $unit->getKey(),
        ]);
        $homeChurch = HomeChurch::factory()->for($church)->create(['status' => HomeChurchStatus::Active]);

        $this->withHeaders($headers)
            ->getJson("/api/v1/admin/church/home-churches/{$homeChurch->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $homeChurch->public_id);

        $this->withHeaders($headers)->postJson("/api/v1/admin/church/home-churches/{$homeChurch->public_id}/status", [
            'status' => 'suspended',
        ])->assertStatus(422);

        $this->withHeaders($headers)->postJson("/api/v1/admin/church/home-churches/{$homeChurch->public_id}/status", [
            'status' => 'suspended',
            'reason' => 'Leadership gap pending reassignment.',
        ])->assertOk()->assertJsonPath('data.status', 'suspended');
    }

    public function test_church_unpublish_group_create_and_forbidden_member_access(): void
    {
        $unit = AdministrativeUnit::factory()->create();
        $location = Location::factory()->create([
            'country_id' => $unit->country_id,
            'administrative_unit_id' => $unit->getKey(),
        ]);
        $scope = new ScopeReference('administrative_unit', $unit->public_id);
        $actor = $this->actorWithPermissions([
            'church.churches.view',
            'church.churches.manage',
        ], $scope);
        $this->authenticate($actor);
        $headers = $this->headers($scope);
        $church = Church::factory()->create([
            'location_id' => $location->getKey(),
            'administrative_unit_id' => $unit->getKey(),
        ]);

        $this->withHeaders($headers)->postJson("/api/v1/admin/church/churches/{$church->public_id}/status", [
            'status' => 'unpublished',
        ])->assertStatus(422);

        $this->withHeaders($headers)->postJson("/api/v1/admin/church/churches/{$church->public_id}/status", [
            'status' => 'unpublished',
            'reason' => 'Seasonal closure of public listing.',
        ])->assertOk()->assertJsonPath('data.status', 'unpublished');

        $this->withHeaders($headers)->postJson('/api/v1/admin/church/groups', [
            'church_id' => $church->public_id,
            'name' => 'Faith Cell',
            'capacity' => 12,
        ])->assertCreated()->assertJsonPath('data.name', 'Faith Cell');

        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/church/groups?filter[church_id]='.$church->public_id)
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1);
    }

    public function test_mission_operator_can_capture_assign_follow_up_and_complete_soul_journey(): void
    {
        $unit = AdministrativeUnit::factory()->create();
        $location = Location::factory()->create([
            'country_id' => $unit->country_id,
            'administrative_unit_id' => $unit->getKey(),
        ]);
        $crusade = Crusade::factory()->for($location)->create();
        $mentor = Person::factory()->create();
        $teamAssignment = MissionTeamAssignment::factory()->for($crusade)->for($mentor, 'person')->create();
        $scope = new ScopeReference('administrative_unit', $unit->public_id);
        $actor = $this->actorWithPermissions([
            'mission.crusades.view', 'mission.souls.view', 'mission.souls.capture',
            'mission.mentors.assign', 'mission.follow_up.record', 'mission.follow_up.complete',
        ], $scope);
        $this->authenticate($actor);
        $headers = $this->headers($scope);

        $soulId = $this->withHeaders([...$headers, 'Idempotency-Key' => 'capture-soul-0001'])
            ->postJson("/api/v1/admin/mission/crusades/{$crusade->public_id}/souls", [
                'given_name' => 'Ada', 'family_name' => 'Mensah',
            ])->assertCreated()->assertJsonPath('data.status', 'new')->json('data.id');

        $mentorAssignmentId = $this->withHeaders([...$headers, 'Idempotency-Key' => 'assign-mentor-0001'])
            ->postJson("/api/v1/admin/mission/souls/{$soulId}/mentor-assignment", [
                'mission_team_assignment_id' => $teamAssignment->public_id,
            ])->assertCreated()->json('data.id');

        $this->withHeaders([...$headers, 'Idempotency-Key' => 'follow-up-0001'])
            ->postJson("/api/v1/admin/mission/souls/{$soulId}/follow-ups", [
                'mentor_assignment_id' => $mentorAssignmentId,
                'channel_code' => 'phone', 'outcome_code' => 'connected',
                'occurred_at' => now()->subMinute()->toIso8601String(),
            ])->assertCreated()->assertJsonPath('data.outcome_code', 'connected');

        $this->withHeaders($headers)->postJson("/api/v1/admin/mission/souls/{$soulId}/follow-up-completion", [
            'reason_code' => 'discipleship_connected',
        ])->assertOk()->assertJsonPath('data.status', 'follow_up_completed');

        $this->withHeaders($headers)->getJson('/api/v1/admin/mission/souls')
            ->assertOk()->assertJsonPath('meta.pagination.total', 1);

        $this->assertTrue(AuditEvent::query()->where('action', 'mission.soul.follow_up_completed')->where('target_id', $soulId)->exists());
    }

    public function test_cross_scope_mission_mutation_returns_404(): void
    {
        $allowedUnit = AdministrativeUnit::factory()->create();
        $otherUnit = AdministrativeUnit::factory()->create();
        $location = Location::factory()->create([
            'country_id' => $otherUnit->country_id,
            'administrative_unit_id' => $otherUnit->getKey(),
        ]);
        $crusade = Crusade::factory()->for($location)->create();
        $scope = new ScopeReference('administrative_unit', $allowedUnit->public_id);
        $actor = $this->actorWithPermissions(['mission.souls.capture'], $scope);
        $this->authenticate($actor);

        $this->withHeaders([...$this->headers($scope), 'Idempotency-Key' => 'capture-soul-0002'])
            ->postJson("/api/v1/admin/mission/crusades/{$crusade->public_id}/souls", [
                'given_name' => 'Hidden', 'family_name' => 'Person',
            ])->assertNotFound();
    }

    public function test_requires_permission_and_scope(): void
    {
        $actor = User::factory()->create();
        $this->authenticate($actor);

        $this->withHeaders($this->headers(new ScopeReference('global', 'platform')))
            ->getJson('/api/v1/admin/church/churches')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'AUTH_PERMISSION_DENIED');
    }

    public function test_first_timer_catalog_returns_person_and_church_names(): void
    {
        $church = Church::factory()->create(['name' => 'Covenant Place']);
        $person = Person::factory()->withProfile()->create();
        $person->profile->forceFill([
            'given_name' => 'Daniel',
            'family_name' => 'Dandeli',
            'preferred_name' => null,
        ])->save();
        User::factory()->for($person, 'person')->create([
            'name' => 'Daniel Dandeli',
            'email' => 'daniel@example.test',
        ]);
        FirstTimer::factory()->create([
            'person_id' => $person->getKey(),
            'church_id' => $church->getKey(),
        ]);
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['church.first_timers.view'], $scope);
        $this->authenticate($actor);

        $this->withHeaders($this->headers($scope))
            ->getJson('/api/v1/admin/church/first-timers')
            ->assertOk()
            ->assertJsonPath('data.0.person_name', 'Daniel Dandeli')
            ->assertJsonPath('data.0.person_email', 'daniel@example.test')
            ->assertJsonPath('data.0.church_name', 'Covenant Place');
    }

    public function test_prayer_and_need_catalogs_can_be_listed_and_transitioned(): void
    {
        $person = Person::factory()->withProfile()->create();
        $person->profile->forceFill([
            'given_name' => 'Mary',
            'family_name' => 'Okafor',
            'preferred_name' => null,
        ])->save();
        $prayer = new PrayerRequest;
        $prayer->forceFill([
            'person_id' => $person->getKey(),
            'subject' => 'Healing for my mother',
            'body' => 'Please pray for complete healing.',
            'status' => 'open',
        ])->save();
        $need = new PastoralNeed;
        $need->forceFill([
            'person_id' => $person->getKey(),
            'category' => 'education',
            'summary' => 'School fees support',
            'status' => 'open',
        ])->save();
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['church.follow_up.view', 'church.follow_up.complete'], $scope);
        $this->authenticate($actor);
        $headers = $this->headers($scope);

        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/church/prayer-requests')
            ->assertOk()
            ->assertJsonPath('data.0.subject', 'Healing for my mother')
            ->assertJsonPath('data.0.person_name', 'Mary Okafor');

        $this->withHeaders($headers)
            ->postJson("/api/v1/admin/church/prayer-requests/{$prayer->public_id}/transitions", [
                'status' => 'assigned',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'assigned');

        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/church/pastoral-needs')
            ->assertOk()
            ->assertJsonPath('data.0.summary', 'School fees support')
            ->assertJsonPath('data.0.person_name', 'Mary Okafor');

        $this->withHeaders($headers)
            ->postJson("/api/v1/admin/church/pastoral-needs/{$need->public_id}/transitions", [
                'status' => 'approved',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_prayer_pastoral_and_follow_up_records_can_be_updated(): void
    {
        $person = Person::factory()->withProfile()->create();
        $person->profile->forceFill([
            'given_name' => 'Mary',
            'family_name' => 'Okafor',
            'preferred_name' => null,
        ])->save();
        $church = Church::factory()->create();
        $firstTimer = FirstTimer::factory()->for($church)->for($person)->create();
        $prayer = new PrayerRequest;
        $prayer->forceFill([
            'person_id' => $person->getKey(),
            'subject' => 'Healing for my mother',
            'body' => 'Please pray for complete healing.',
            'status' => 'open',
        ])->save();
        $need = new PastoralNeed;
        $need->forceFill([
            'person_id' => $person->getKey(),
            'category' => 'education',
            'summary' => 'School fees support',
            'status' => 'open',
        ])->save();
        $task = new FollowUpTask;
        $task->forceFill([
            'first_timer_id' => $firstTimer->getKey(),
            'type' => FollowUpTaskType::FirstTimerContact,
            'status' => FollowUpTaskStatus::Pending,
            'due_at' => now()->utc()->addDays(2),
        ])->save();

        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['church.follow_up.view', 'church.follow_up.complete'], $scope);
        $this->authenticate($actor);
        $headers = $this->headers($scope);

        $this->withHeaders($headers)
            ->putJson("/api/v1/admin/church/prayer-requests/{$prayer->public_id}", [
                'subject' => 'Updated prayer subject',
                'body' => 'Updated prayer body.',
                'status' => 'answered',
            ])
            ->assertOk()
            ->assertJsonPath('data.subject', 'Updated prayer subject')
            ->assertJsonPath('data.status', 'answered');

        $this->withHeaders($headers)
            ->putJson("/api/v1/admin/church/pastoral-needs/{$need->public_id}", [
                'category' => 'medical',
                'summary' => 'Hospital bill support',
                'status' => 'closed',
            ])
            ->assertOk()
            ->assertJsonPath('data.category', 'medical')
            ->assertJsonPath('data.status', 'closed');

        $assignee = Person::factory()->withProfile()->create();
        $this->withHeaders($headers)
            ->putJson("/api/v1/admin/church/follow-up-tasks/{$task->public_id}", [
                'assigned_to_person_id' => $assignee->public_id,
                'due_at' => now()->utc()->addDays(5)->toIso8601String(),
            ])
            ->assertOk()
            ->assertJsonPath('data.assigned_to_person_id', $assignee->public_id);
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

    /** @return array<string, string> */
    private function headers(ScopeReference $scope): array
    {
        return ['X-Scope-Type' => $scope->type, 'X-Scope-ID' => $scope->key];
    }
}
