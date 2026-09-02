<?php

namespace Tests\Feature\Admin;

use App\Models\KcaApplication;
use App\Models\KcaEnrollment;
use App\Models\KcaOrientationStep;
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

class KcaOrientationStepsApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_list_and_update_orientation_steps(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.governance.view', 'kca.governance.manage'], $scope);
        $this->authenticate($actor);

        $step = KcaOrientationStep::query()->where('slug', 'overview')->first();
        $this->assertNotNull($step);

        $this->withHeaders($this->headers($scope))
            ->getJson('/api/v1/admin/kca/orientation-steps')
            ->assertOk()
            ->assertJsonPath('data.steps.0.slug', 'overview');

        $this->withHeaders($this->headers($scope))
            ->putJson('/api/v1/admin/kca/orientation-steps', [
                'steps' => KcaOrientationStep::query()->ordered()->get()->map(fn (KcaOrientationStep $row): array => [
                    'id' => $row->public_id,
                    'slug' => $row->slug,
                    'title' => $row->slug === 'overview' ? 'Our Vision & Mission' : $row->title,
                    'subtitle' => $row->subtitle,
                    'body' => $row->body,
                    'display_type' => $row->display_type,
                    'sequence' => $row->sequence,
                    'is_active' => true,
                ])->all(),
            ])
            ->assertOk()
            ->assertJsonPath('data.steps.0.title', 'Our Vision & Mission');

        $this->assertSame(
            'Our Vision & Mission',
            KcaOrientationStep::query()->where('slug', 'overview')->value('title'),
        );
    }

    public function test_enrolled_student_can_revisit_orientation_after_completion(): void
    {
        $user = User::factory()->withPerson()->create();
        $person = $user->person;
        $this->assertNotNull($person);

        KcaEnrollment::factory()->for($person)->create();
        KcaApplication::factory()->for($person)->create([
            'status' => 'accepted',
            'orientation_progress' => ['overview', 'rules', 'path', 'mentors'],
            'orientation_completed_at' => now()->utc(),
        ]);

        $this->authenticate($user);

        $this->getJson('/api/v1/user/kca/orientation')
            ->assertOk()
            ->assertJsonPath('data.review_mode', true)
            ->assertJsonPath('data.can_complete', false)
            ->assertJsonPath('data.stages.0.title', fn (mixed $value): bool => is_string($value) && $value !== '');
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
