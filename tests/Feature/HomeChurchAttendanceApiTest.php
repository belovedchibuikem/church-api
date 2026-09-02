<?php

namespace Tests\Feature;

use App\Models\AdministrativeUnit;
use App\Models\Church;
use App\Models\HomeChurch;
use App\Models\HomeChurchAttendanceRecord;
use App\Models\Location;
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

class HomeChurchAttendanceApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_church_operator_can_record_attendance_with_gender_breakdown(): void
    {
        $unit = AdministrativeUnit::factory()->create();
        $location = Location::factory()->create([
            'country_id' => $unit->country_id,
            'administrative_unit_id' => $unit->getKey(),
        ]);
        $scope = new ScopeReference('administrative_unit', $unit->public_id);
        $actor = $this->actorWithPermissions(['church.churches.manage', 'church.home_churches.view'], $scope);
        $this->authenticate($actor);
        $headers = $this->headers($scope);
        $church = Church::factory()->create([
            'location_id' => $location->getKey(),
            'administrative_unit_id' => $unit->getKey(),
        ]);
        $homeChurch = HomeChurch::factory()->for($church)->create();

        $this->withHeaders($headers)
            ->postJson('/api/v1/admin/church/attendance', [
                'home_church_id' => $homeChurch->public_id,
                'service_date' => '2026-08-30',
                'males' => 11,
                'females' => 7,
                'children' => 7,
                'notes' => 'Sunday service',
            ])
            ->assertCreated()
            ->assertJsonPath('data.males', 11)
            ->assertJsonPath('data.females', 7)
            ->assertJsonPath('data.children', 7)
            ->assertJsonPath('data.total', 25)
            ->assertJsonPath('data.adults', 18);

        $record = HomeChurchAttendanceRecord::query()
            ->where('home_church_id', $homeChurch->getKey())
            ->whereDate('service_date', '2026-08-30')
            ->firstOrFail();

        $this->assertSame(11, $record->males);
        $this->assertSame(7, $record->females);
        $this->assertSame(18, $record->adults);
        $this->assertSame(25, $record->headcount());
    }

    public function test_duplicate_attendance_date_updates_existing_session(): void
    {
        $unit = AdministrativeUnit::factory()->create();
        $location = Location::factory()->create([
            'country_id' => $unit->country_id,
            'administrative_unit_id' => $unit->getKey(),
        ]);
        $scope = new ScopeReference('administrative_unit', $unit->public_id);
        $actor = $this->actorWithPermissions(['church.churches.manage'], $scope);
        $this->authenticate($actor);
        $headers = $this->headers($scope);
        $church = Church::factory()->create([
            'location_id' => $location->getKey(),
            'administrative_unit_id' => $unit->getKey(),
        ]);
        $homeChurch = HomeChurch::factory()->for($church)->create();

        $payload = [
            'home_church_id' => $homeChurch->public_id,
            'service_date' => '2026-08-30',
            'males' => 10,
            'females' => 8,
            'children' => 5,
        ];

        $this->withHeaders($headers)->postJson('/api/v1/admin/church/attendance', $payload)->assertCreated();
        $this->withHeaders($headers)->postJson('/api/v1/admin/church/attendance', [
            ...$payload,
            'males' => 12,
            'females' => 6,
            'children' => 4,
        ])->assertCreated()->assertJsonPath('data.total', 22);

        $this->assertSame(1, HomeChurchAttendanceRecord::query()->where('home_church_id', $homeChurch->getKey())->count());
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
        return [
            'X-Scope-Type' => $scope->type,
            'X-Scope-ID' => $scope->key,
        ];
    }
}
