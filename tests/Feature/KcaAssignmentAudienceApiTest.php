<?php

namespace Tests\Feature;

use App\Models\KcaAssignment;
use App\Models\KcaCohort;
use App\Models\KcaEnrollment;
use App\Models\KcaLesson;
use App\Models\KcaModule;
use App\Models\KcaYear;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\GrantPermissionToRoleAction;
use App\Support\Authorization\ScopeReference;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KcaAssignmentAudienceApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_operator_can_assign_to_one_student(): void
    {
        [$scope, $headers, $module, $lesson, $enrollment] = $this->seedContext();

        $this->withHeaders($headers)
            ->postJson('/api/v1/admin/kca/assignments', [
                'audience' => 'student',
                'kca_enrollment_id' => $enrollment->public_id,
                'kca_module_id' => $module->public_id,
                'kca_lesson_id' => $lesson->public_id,
                'title' => 'Personal reflection',
                'assignment_kind' => 'written',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Personal reflection')
            ->assertJsonPath('data.kca_enrollment_id', $enrollment->public_id)
            ->assertJsonPath('data.status', 'assigned');

        $this->assertSame(1, KcaAssignment::query()->count());
        $this->assertSame('assigned', KcaAssignment::query()->first()?->state->value);
        $this->assertNotNull(KcaAssignment::query()->first()?->assigned_at);
    }

    public function test_operator_can_assign_to_a_cohort(): void
    {
        [$scope, $headers, $module, $lesson] = $this->seedContext(withEnrollment: false);
        $year = KcaYear::factory()->create();
        $cohort = KcaCohort::factory()->for($year, 'year')->create();
        $otherCohort = KcaCohort::factory()->for($year, 'year')->create();

        KcaEnrollment::factory()->for($cohort, 'cohort')->count(2)->create();
        KcaEnrollment::factory()->for($otherCohort, 'cohort')->create();

        $this->withHeaders($headers)
            ->postJson('/api/v1/admin/kca/assignments', [
                'audience' => 'A cohort',
                'cohort_id' => $cohort->public_id,
                'kca_module_id' => $module->public_id,
                'kca_lesson_id' => $lesson->public_id,
                'title' => 'Cohort practical',
                'assignment_kind' => 'practical',
            ])
            ->assertCreated()
            ->assertJsonPath('data.created', 2)
            ->assertJsonPath('data.audience', 'cohort');

        $this->assertSame(2, KcaAssignment::query()->count());
        $this->assertSame(
            2,
            KcaAssignment::query()->where('title', 'Cohort practical')->count(),
        );
    }

    public function test_operator_can_save_assignment_as_draft_and_publish(): void
    {
        [$scope, $headers, $module, $lesson, $enrollment] = $this->seedContext();

        $this->withHeaders($headers)
            ->postJson('/api/v1/admin/kca/assignments', [
                'audience' => 'student',
                'kca_enrollment_id' => $enrollment->public_id,
                'kca_module_id' => $module->public_id,
                'kca_lesson_id' => $lesson->public_id,
                'title' => 'Draft reflection',
                'as_draft' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');

        $assignment = KcaAssignment::query()->firstOrFail();
        $this->assertSame('draft', $assignment->state->value);
        $this->assertNull($assignment->assigned_at);

        $this->withHeaders($headers)
            ->postJson("/api/v1/admin/kca/assignments/{$assignment->public_id}/transitions", [
                'status' => 'assigned',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'assigned');

        $assignment->refresh();
        $this->assertSame('assigned', $assignment->state->value);
        $this->assertNotNull($assignment->assigned_at);
    }

    public function test_publish_aliases_are_accepted_and_legacy_rows_hydrate(): void
    {
        [$scope, $headers, $module, $lesson, $enrollment] = $this->seedContext();

        $this->withHeaders($headers)
            ->postJson('/api/v1/admin/kca/assignments', [
                'audience' => 'student',
                'kca_enrollment_id' => $enrollment->public_id,
                'kca_module_id' => $module->public_id,
                'kca_lesson_id' => $lesson->public_id,
                'title' => 'Alias publish',
                'as_draft' => true,
            ])
            ->assertCreated();

        $assignment = KcaAssignment::query()->firstOrFail();

        $this->withHeaders($headers)
            ->postJson("/api/v1/admin/kca/assignments/{$assignment->public_id}/transitions", [
                'status' => 'published',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'assigned');

        DB::table('kca_assignments')->where('id', $assignment->getKey())->update(['state' => 'publised']);
        $hydrated = KcaAssignment::query()->findOrFail($assignment->getKey());
        $this->assertSame('assigned', $hydrated->state->value);
    }

    public function test_operator_can_show_update_and_delete_an_assignment(): void
    {
        [$scope, $headers, $module, $lesson, $enrollment] = $this->seedContext();

        $this->withHeaders($headers)
            ->postJson('/api/v1/admin/kca/assignments', [
                'audience' => 'student',
                'kca_enrollment_id' => $enrollment->public_id,
                'kca_module_id' => $module->public_id,
                'kca_lesson_id' => $lesson->public_id,
                'title' => 'CRUD assignment',
                'assignment_kind' => 'written',
            ])
            ->assertCreated();

        $assignment = KcaAssignment::query()->firstOrFail();

        $this->withHeaders($headers)
            ->getJson("/api/v1/admin/kca/assignments/{$assignment->public_id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'CRUD assignment')
            ->assertJsonPath('data.kca_enrollment_id', $enrollment->public_id)
            ->assertJsonPath('data.assignment_kind', 'written')
            ->assertJsonPath('data.status', 'assigned');

        $this->withHeaders($headers)
            ->patchJson("/api/v1/admin/kca/assignments/{$assignment->public_id}", [
                'title' => 'Updated CRUD assignment',
                'assignment_kind' => 'practical',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated CRUD assignment')
            ->assertJsonPath('data.assignment_kind', 'practical');

        $this->withHeaders($headers)
            ->deleteJson("/api/v1/admin/kca/assignments/{$assignment->public_id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('kca_assignments', ['id' => $assignment->getKey()]);
        $this->withHeaders($headers)
            ->getJson("/api/v1/admin/kca/assignments/{$assignment->public_id}")
            ->assertNotFound();
    }

    public function test_operator_can_assign_to_all_enrolled_students(): void
    {
        [$scope, $headers, $module, $lesson] = $this->seedContext(withEnrollment: false);
        $year = KcaYear::factory()->create();
        $cohortA = KcaCohort::factory()->for($year, 'year')->create();
        $cohortB = KcaCohort::factory()->for($year, 'year')->create();
        KcaEnrollment::factory()->for($cohortA, 'cohort')->count(2)->create();
        KcaEnrollment::factory()->for($cohortB, 'cohort')->create();

        $this->withHeaders($headers)
            ->postJson('/api/v1/admin/kca/assignments', [
                'audience' => 'all',
                'kca_module_id' => $module->public_id,
                'kca_lesson_id' => $lesson->public_id,
                'title' => 'All students assignment',
                'assignment_kind' => 'standard',
            ])
            ->assertCreated()
            ->assertJsonPath('data.created', 3)
            ->assertJsonPath('data.audience', 'all');

        $this->assertSame(3, KcaAssignment::query()->where('title', 'All students assignment')->count());
    }

    public function test_cohort_audience_requires_cohort_id(): void
    {
        [$scope, $headers, $module, $lesson] = $this->seedContext(withEnrollment: false);

        $this->withHeaders($headers)
            ->postJson('/api/v1/admin/kca/assignments', [
                'audience' => 'cohort',
                'kca_module_id' => $module->public_id,
                'kca_lesson_id' => $lesson->public_id,
                'title' => 'Missing cohort',
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['Select the cohort to assign.']);
    }

    /**
     * @return array{0: ScopeReference, 1: array<string, string>, 2: KcaModule, 3: KcaLesson, 4?: KcaEnrollment}
     */
    private function seedContext(bool $withEnrollment = true): array
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.assignments.transition', 'kca.enrollments.view'], $scope);
        $this->authenticate($actor);
        $module = KcaModule::factory()->create();
        $lesson = KcaLesson::factory()->for($module, 'module')->create();
        $headers = $this->headers($scope);

        if (! $withEnrollment) {
            return [$scope, $headers, $module, $lesson];
        }

        $enrollment = KcaEnrollment::factory()->create();

        return [$scope, $headers, $module, $lesson, $enrollment];
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
