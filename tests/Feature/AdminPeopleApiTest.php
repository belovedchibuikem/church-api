<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\Convert;
use App\Models\CounsellingCase;
use App\Models\Permission;
use App\Models\Person;
use App\Models\PersonProfile;
use App\Models\Role;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\GrantPermissionToRoleAction;
use App\Support\Authorization\ScopeReference;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminPeopleApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_directory_lists_a_member_once_with_public_id_and_roles(): void
    {
        $person = Person::factory()->withProfile()->create();
        $church = Church::factory()->create();
        ChurchMembership::factory()->create([
            'person_id' => $person->getKey(),
            'church_id' => $church->getKey(),
            'status' => 'active',
        ]);
        Convert::query()->create([
            'person_id' => $person->getKey(),
            'church_id' => $church->getKey(),
            'converted_at' => now()->utc(),
            'status' => 'active',
        ]);

        $actor = $this->actorWithPermissions(['church.churches.view', 'church.churches.manage']);
        $this->authenticate($actor);
        $headers = $this->headers();

        $list = $this->withHeaders($headers)->getJson('/api/v1/admin/church/people')->assertOk();
        $rows = collect($list->json('data'))->where('id', $person->public_id);
        $this->assertSame(1, $rows->count());
        $row = $rows->first();
        $this->assertNotEmpty($row['id']);
        $this->assertStringContainsString('Member', $row['type']);
        $this->assertStringContainsString('Convert', $row['type']);
        $this->assertSame($church->name, $row['church_name']);

        $this->withHeaders($headers)->getJson("/api/v1/admin/church/people/{$person->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $person->public_id)
            ->assertJsonPath('data.preferred_name', null);

        $this->withHeaders($headers)->getJson('/api/v1/admin/dashboards/people')
            ->assertOk()
            ->assertJsonPath('data.metrics.0.label', 'Total People')
            ->assertJsonPath('data.metrics.3.label', 'Converted');

        $dashboard = $this->withHeaders($headers)->getJson('/api/v1/admin/dashboards/people')->json('data');
        $numeric = static fn (string $value): int => (int) str_replace(',', '', $value);
        $total = $numeric($dashboard['metrics'][0]['value']);
        $active = $numeric($dashboard['metrics'][1]['value']);
        $converted = $numeric($dashboard['metrics'][3]['value']);
        $this->assertGreaterThanOrEqual(1, $total);
        $this->assertLessThanOrEqual($total, $active);
        $this->assertLessThanOrEqual($total, $converted);
        $this->assertNotEmpty($dashboard['metrics'][0]['hint']);
        $this->assertNotEmpty($dashboard['definitions']);
    }

    public function test_create_offers_matches_then_merge_and_archive(): void
    {
        $existing = Person::factory()->create();
        PersonProfile::factory()->create([
            'person_id' => $existing->getKey(),
            'given_name' => 'Ada',
            'family_name' => 'Nwosu',
        ]);
        $duplicate = Person::factory()->withProfile()->create();

        $actor = $this->actorWithPermissions(['church.churches.view', 'church.churches.manage']);
        $this->authenticate($actor);
        $headers = $this->headers();

        $this->withHeaders($headers)->postJson('/api/v1/admin/church/people', [
            'given_name' => 'Ada',
            'family_name' => 'Nwosu',
        ])->assertStatus(409)->assertJsonPath('data.requires_confirmation', true);

        $createdId = $this->withHeaders($headers)->postJson('/api/v1/admin/church/people', [
            'given_name' => 'Chioma',
            'family_name' => 'Okeke',
            'confirm_new' => true,
        ])->assertCreated()->json('data.id');

        $this->withHeaders($headers)->postJson("/api/v1/admin/church/people/{$createdId}/merge", [
            'source_person_id' => $duplicate->public_id,
        ])->assertOk()->assertJsonPath('data.id', $createdId);

        $this->assertNotNull($duplicate->fresh()->archived_at);

        $this->withHeaders($headers)->postJson("/api/v1/admin/church/people/{$createdId}/archive", [
            'reason' => 'inactive_record',
        ])->assertOk()->assertJsonPath('data.status', 'archived');
    }

    public function test_counselling_list_hides_client_and_unauthenticated_is_401(): void
    {
        $case = CounsellingCase::query()->create([
            'church_id' => Church::factory()->create()->getKey(),
            'client_person_id' => Person::factory()->withProfile()->create()->getKey(),
            'case_type' => 'general',
            'status' => 'open',
            'summary' => 'Secret pastoral note',
            'opened_at' => now()->utc(),
        ]);

        $this->getJson('/api/v1/admin/church/people')->assertUnauthorized();

        $viewer = $this->actorWithPermissions(['church.follow_up.view']);
        $this->authenticate($viewer);
        $list = $this->withHeaders($this->headers())->getJson('/api/v1/admin/church/counselling-cases')->assertOk();
        $row = collect($list->json('data'))->firstWhere('id', $case->public_id);
        $this->assertSame('Restricted', $row['client_label']);
        $this->assertArrayNotHasKey('person_name', $row);
        $this->assertArrayNotHasKey('summary', $row);

        $this->withHeaders($this->headers())->getJson("/api/v1/admin/church/counselling-cases/{$case->public_id}")
            ->assertForbidden();
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
