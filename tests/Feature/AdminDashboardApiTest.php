<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\Crusade;
use App\Models\HomeChurch;
use App\Models\KcaApplication;
use App\Models\MissionSoulJourney;
use App\Models\Permission;
use App\Models\PressPublication;
use App\Models\Role;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\GrantPermissionToRoleAction;
use App\Support\Authorization\ScopeReference;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminDashboardApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_global_dashboard_returns_live_aggregates(): void
    {
        Church::factory()->count(2)->create();
        HomeChurch::factory()->count(3)->create();
        ChurchMembership::factory()->count(4)->create(['status' => 'active']);

        $expectedChurches = Church::query()->count();
        $expectedHomeChurches = HomeChurch::query()->count();
        $expectedMembers = ChurchMembership::query()->where('status', 'active')->count();

        $actor = $this->actorWithPermissions(['identity.users.view']);
        $this->authenticate($actor);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/admin/dashboards/global')
            ->assertOk()
            ->assertJsonPath('data.metrics.0.label', 'Total Churches')
            ->assertJsonPath('data.metrics.0.value', number_format($expectedChurches))
            ->assertJsonPath('data.metrics.1.label', 'Home Churches')
            ->assertJsonPath('data.metrics.1.value', number_format($expectedHomeChurches))
            ->assertJsonPath('data.metrics.2.label', 'Members')
            ->assertJsonPath('data.metrics.2.value', number_format($expectedMembers))
            ->assertJsonStructure([
                'data' => [
                    'metrics',
                    'breakdown',
                    'series',
                    'recent_activities',
                ],
            ]);
    }

    public function test_church_dashboard_is_scoped_to_assigned_church(): void
    {
        $church = Church::factory()->create();
        $otherChurch = Church::factory()->create();
        ChurchMembership::factory()->for($church)->count(2)->create(['status' => 'active']);
        ChurchMembership::factory()->for($otherChurch)->count(5)->create(['status' => 'active']);

        $scope = new ScopeReference('church', $church->public_id);
        $actor = $this->actorWithPermissions(['church.churches.view'], $scope);
        $this->authenticate($actor);

        $this->withHeaders($this->headers($scope))
            ->getJson('/api/v1/admin/dashboards/church')
            ->assertOk()
            ->assertJsonPath('data.metrics.0.label', 'Total Members')
            ->assertJsonPath('data.metrics.0.value', '2');
    }

    public function test_kca_and_mission_dashboards_return_domain_metrics(): void
    {
        KcaApplication::factory()->count(2)->create();
        $crusade = Crusade::factory()->create();
        MissionSoulJourney::factory()->for($crusade)->count(3)->create();
        PressPublication::factory()->count(2)->create();

        $expectedApplications = KcaApplication::query()->count();
        $expectedSouls = MissionSoulJourney::query()->count();
        $expectedPublications = PressPublication::query()->count();

        $actor = $this->actorWithPermissions([
            'kca.enrollments.view',
            'mission.crusades.view',
            'press.publications.view',
        ]);
        $this->authenticate($actor);
        $headers = $this->headers();

        $this->withHeaders($headers)->getJson('/api/v1/admin/dashboards/kca')
            ->assertOk()
            ->assertJsonPath('data.metrics.0.label', 'Applications')
            ->assertJsonPath('data.metrics.0.value', number_format($expectedApplications));

        $this->withHeaders($headers)->getJson('/api/v1/admin/dashboards/mission')
            ->assertOk()
            ->assertJsonPath('data.metrics.1.label', 'Souls Captured')
            ->assertJsonPath('data.metrics.1.value', number_format($expectedSouls));

        $this->withHeaders($headers)->getJson('/api/v1/admin/dashboards/press')
            ->assertOk()
            ->assertJsonPath('data.metrics.0.label', 'Publications')
            ->assertJsonPath('data.metrics.0.value', number_format($expectedPublications));
    }

    public function test_unknown_dashboard_module_returns_404(): void
    {
        $actor = $this->actorWithPermissions(['identity.users.view']);
        $this->authenticate($actor);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/admin/dashboards/unknown')
            ->assertNotFound();
    }

    /** @param array<int, string> $permissionCodes */
    private function actorWithPermissions(array $permissionCodes, ?ScopeReference $scope = null): User
    {
        $actor = User::factory()->create();
        $role = Role::factory()->create();

        foreach ($permissionCodes as $code) {
            $permission = Permission::factory()->create(['code' => $code]);
            $this->app->make(GrantPermissionToRoleAction::class)->handle($role, $permission);
        }

        $assignment = $this->app->make(AssignRoleToUserAction::class)->handle($actor, $role);
        $this->app->make(AssignScopeToRoleAssignmentAction::class)->handle(
            $assignment,
            $scope ?? new ScopeReference('global', 'platform'),
        );

        return $actor;
    }

    private function authenticate(User $user): void
    {
        $session = SecuritySession::factory()->for($user)->create();
        $this->actingAs($user)->withSession([
            'security_session_id' => $session->public_id,
            'auth.mfa_verified_at' => now()->utc()->toIso8601String(),
        ]);
    }

  /** @return array<string, string> */
    private function headers(?ScopeReference $scope = null): array
    {
        $scope ??= new ScopeReference('global', 'platform');

        return [
            'X-Scope-Type' => $scope->type,
            'X-Scope-ID' => $scope->key,
        ];
    }
}
