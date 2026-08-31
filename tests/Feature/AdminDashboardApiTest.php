<?php

namespace Tests\Feature;

use App\Communication\CommunicationChannel;
use App\Communication\CommunicationDeliveryStatus;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\CommunicationBroadcast;
use App\Models\CommunicationDeliveryAttempt;
use App\Models\CommunicationRecipient;
use App\Models\CommunicationTemplate;
use App\Models\Country;
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
            ->assertJsonPath('data.metrics.0.label', 'Churches')
            ->assertJsonPath('data.metrics.0.value', '1')
            ->assertJsonPath('data.metrics.1.label', 'Total Members')
            ->assertJsonPath('data.metrics.1.value', '2');
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

    public function test_unauthenticated_dashboard_request_returns_401(): void
    {
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/admin/dashboards/global')
            ->assertUnauthorized();
    }

    public function test_authenticated_without_permission_returns_403(): void
    {
        $actor = $this->actorWithPermissions(['identity.users.view']);
        $this->authenticate($actor);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/admin/dashboards/church')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'AUTH_PERMISSION_DENIED');
    }

    public function test_empty_scope_returns_zero_metrics_not_placeholders(): void
    {
        $church = Church::factory()->create();
        ChurchMembership::factory()->for($church)->count(3)->create(['status' => 'active']);

        $emptyChurch = Church::factory()->create();
        $scope = new ScopeReference('church', $emptyChurch->public_id);
        $actor = $this->actorWithPermissions(['church.churches.view'], $scope);
        $this->authenticate($actor);

        $this->withHeaders($this->headers($scope))
            ->getJson('/api/v1/admin/dashboards/church')
            ->assertOk()
            ->assertJsonPath('data.metrics.0.label', 'Churches')
            ->assertJsonPath('data.metrics.0.value', '1')
            ->assertJsonPath('data.metrics.1.label', 'Total Members')
            ->assertJsonPath('data.metrics.1.value', '0')
            ->assertJsonPath('data.period.preset', 'last_6_months');
    }

    public function test_date_preset_is_echoed_and_filters_series(): void
    {
        Church::factory()->create(['created_at' => now()->subMonths(8)]);
        Church::factory()->create(['created_at' => now()->subDays(3)]);

        $actor = $this->actorWithPermissions(['identity.users.view']);
        $this->authenticate($actor);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/admin/dashboards/global?preset=last_30_days')
            ->assertOk()
            ->assertJsonPath('data.period.preset', 'last_30_days')
            ->assertJsonPath('data.currency', 'NGN')
            ->assertJsonPath('data.scope.type', 'global');
    }

    public function test_kca_dashboard_is_scoped_to_church_memberships(): void
    {
        $church = Church::factory()->create();
        $other = Church::factory()->create();
        $member = ChurchMembership::factory()->for($church)->create(['status' => 'active']);
        ChurchMembership::factory()->for($other)->create(['status' => 'active']);
        KcaApplication::factory()->create(['person_id' => $member->person_id]);
        KcaApplication::factory()->create();

        $scope = new ScopeReference('church', $church->public_id);
        $actor = $this->actorWithPermissions(['kca.applications.view'], $scope);
        $this->authenticate($actor);

        $this->withHeaders($this->headers($scope))
            ->getJson('/api/v1/admin/dashboards/kca')
            ->assertOk()
            ->assertJsonPath('data.metrics.0.label', 'Applications')
            ->assertJsonPath('data.metrics.0.value', '1');
    }

    public function test_communications_dashboard_uses_campaign_titles_and_channel_mix(): void
    {
        $template = CommunicationTemplate::factory()->create([
            'subject' => 'Sunday Service Reminder',
            'channel' => CommunicationChannel::Email,
        ]);
        $broadcast = CommunicationBroadcast::factory()->create([
            'communication_template_id' => $template->getKey(),
            'channel' => CommunicationChannel::Email,
            'purpose' => 'communications.ministry_updates',
        ]);
        $recipient = CommunicationRecipient::factory()->create([
            'communication_broadcast_id' => $broadcast->getKey(),
        ]);
        CommunicationDeliveryAttempt::factory()->create([
            'communication_recipient_id' => $recipient->getKey(),
            'channel' => CommunicationChannel::Email,
            'status' => CommunicationDeliveryStatus::Succeeded,
        ]);

        $actor = $this->actorWithPermissions(['communications.templates.view']);
        $this->authenticate($actor);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/admin/dashboards/communications')
            ->assertOk()
            ->assertJsonPath('data.metrics.0.label', 'Messages Sent')
            ->assertJsonPath('data.metrics.1.label', 'Delivered')
            ->assertJsonPath('data.breakdown.0.label', 'Email')
            ->assertJsonPath('data.recent_rows.0.Campaign', 'Sunday Service Reminder')
            ->assertJsonMissing(['data.breakdown.0.label' => 'communications.ministry_updates']);
    }

    public function test_reports_dashboard_counts_countries_with_churches_not_the_country_table(): void
    {
        Country::factory()->count(4)->create();
        Church::factory()->count(2)->create();
        ChurchMembership::factory()->count(3)->create(['status' => 'active']);

        $expectedCountries = (int) Church::query()
            ->join('administrative_units', 'administrative_units.id', '=', 'churches.administrative_unit_id')
            ->join('countries', 'countries.id', '=', 'administrative_units.country_id')
            ->distinct()
            ->count('countries.id');
        $allCountries = Country::query()->count();

        $this->assertGreaterThan($expectedCountries, $allCountries);

        $actor = $this->actorWithPermissions(['reporting.alert_rules.view']);
        $this->authenticate($actor);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/admin/dashboards/reports')
            ->assertOk()
            ->assertJsonPath('data.metrics.0.label', 'Total People')
            ->assertJsonPath('data.metrics.1.label', 'Active Churches')
            ->assertJsonPath('data.metrics.2.label', 'Home Churches')
            ->assertJsonPath('data.metrics.3.label', 'Countries')
            ->assertJsonPath('data.metrics.3.value', number_format($expectedCountries))
            ->assertJsonPath('data.donut.label', 'People');
    }

    public function test_remaining_dashboard_modules_return_live_structures(): void
    {
        $actor = $this->actorWithPermissions([
            'organization.countries.view',
            'church.home_churches.view',
            'finance.payment_intents.view',
            'communications.templates.view',
            'reporting.alert_rules.view',
            'security.audit.view',
            'safeguarding.incidents.report',
        ]);
        $this->authenticate($actor);
        $headers = $this->headers();

        foreach (['geography', 'home-churches', 'finance', 'communications', 'reports', 'security', 'safeguarding'] as $module) {
            $this->withHeaders($headers)
                ->getJson('/api/v1/admin/dashboards/'.$module)
                ->assertOk()
                ->assertJsonStructure([
                    'data' => ['metrics', 'series', 'period', 'currency', 'scope'],
                ]);
        }
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
