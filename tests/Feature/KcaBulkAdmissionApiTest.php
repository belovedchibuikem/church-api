<?php

namespace Tests\Feature;

use App\Kca\KcaApplicationState;
use App\Models\KcaApplication;
use App\Models\KcaCohort;
use App\Models\KcaEnrollment;
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
use Tests\TestCase;

class KcaBulkAdmissionApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_operator_can_admit_and_enroll_multiple_applications(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.enrollments.manage', 'kca.applications.transition'], $scope);
        $this->authenticate($actor);

        $year = KcaYear::factory()->create(['code' => 'KCA-2026', 'name' => '2026 KCA Year']);
        $cohort = KcaCohort::factory()->for($year, 'year')->create(['code' => 'KCA-MASS-A']);
        $first = KcaApplication::factory()->create();
        $second = KcaApplication::factory()->create();

        $this->withHeaders($this->headers($scope))
            ->postJson('/api/v1/admin/kca/applications/bulk-enrollments', [
                'application_ids' => [$first->public_id, $second->public_id],
                'cohort_id' => $cohort->public_id,
                'starts_on' => $cohort->starts_on->toDateString(),
                'status' => 'accepted',
            ])
            ->assertOk()
            ->assertJsonPath('data.admitted_count', 2)
            ->assertJsonPath('data.failed_count', 0)
            ->assertJsonPath('data.admitted.0.status', 'accepted')
            ->assertJsonPath('data.admitted.1.status', 'accepted');

        $this->assertSame(2, KcaEnrollment::query()->count());
        $numbers = KcaEnrollment::query()->orderBy('registration_number')->pluck('registration_number')->all();
        $this->assertSame(['KCA-2026-00001', 'KCA-2026-00002'], $numbers);
        $this->assertDatabaseHas('kca_applications', [
            'id' => $first->getKey(),
            'status' => KcaApplicationState::Accepted->value,
        ]);
        $this->assertDatabaseHas('kca_applications', [
            'id' => $second->getKey(),
            'status' => KcaApplicationState::Accepted->value,
        ]);
    }

    public function test_bulk_admit_continues_when_one_application_cannot_enroll(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.enrollments.manage'], $scope);
        $this->authenticate($actor);

        $year = KcaYear::factory()->create(['code' => '2026']);
        $cohort = KcaCohort::factory()->for($year, 'year')->create();
        $ready = KcaApplication::factory()->create();
        $blocked = KcaApplication::factory()->create(['status' => KcaApplicationState::Deferred]);

        $this->withHeaders($this->headers($scope))
            ->postJson('/api/v1/admin/kca/applications/bulk-enrollments', [
                'application_ids' => [$blocked->public_id, $ready->public_id],
                'cohort_id' => $cohort->public_id,
                'starts_on' => now()->toDateString(),
            ])
            ->assertOk()
            ->assertJsonPath('data.admitted_count', 1)
            ->assertJsonPath('data.failed_count', 1)
            ->assertJsonPath('data.failures.0.application_id', $blocked->public_id)
            ->assertJsonPath('data.admitted.0.application_id', $ready->public_id);

        $this->assertSame(1, KcaEnrollment::query()->count());
        $this->assertSame(KcaApplicationState::Deferred, $blocked->refresh()->status);
        $this->assertSame(KcaApplicationState::Accepted, $ready->refresh()->status);
    }

    public function test_bulk_admit_skips_students_already_in_the_same_cohort(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.enrollments.manage'], $scope);
        $this->authenticate($actor);

        $year = KcaYear::factory()->create(['code' => '2026']);
        $cohort = KcaCohort::factory()->for($year, 'year')->create();
        $fresh = KcaApplication::factory()->create();
        $already = KcaApplication::factory()->create();

        $this->withHeaders($this->headers($scope))
            ->postJson('/api/v1/admin/kca/applications/bulk-enrollments', [
                'application_ids' => [$already->public_id],
                'cohort_id' => $cohort->public_id,
                'starts_on' => now()->toDateString(),
            ])
            ->assertOk()
            ->assertJsonPath('data.admitted_count', 1);

        $existingNumber = KcaEnrollment::query()->value('registration_number');

        $this->withHeaders($this->headers($scope))
            ->postJson('/api/v1/admin/kca/applications/bulk-enrollments', [
                'application_ids' => [$already->public_id, $fresh->public_id],
                'cohort_id' => $cohort->public_id,
                'starts_on' => now()->toDateString(),
            ])
            ->assertOk()
            ->assertJsonPath('data.admitted_count', 1)
            ->assertJsonPath('data.skipped_count', 1)
            ->assertJsonPath('data.failed_count', 0);

        $this->assertSame(2, KcaEnrollment::query()->count());
        $this->assertSame($existingNumber, KcaEnrollment::query()->where('kca_application_id', $already->getKey())->value('registration_number'));
    }

    public function test_operator_can_update_status_for_selected_applications(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.applications.transition'], $scope);
        $this->authenticate($actor);

        $first = KcaApplication::factory()->create();
        $second = KcaApplication::factory()->create();
        $alreadyReviewed = KcaApplication::factory()->reviewed()->create();

        $this->withHeaders($this->headers($scope))
            ->postJson('/api/v1/admin/kca/applications/bulk-transitions', [
                'application_ids' => [$first->public_id, $second->public_id, $alreadyReviewed->public_id],
                'status' => 'reviewed',
            ])
            ->assertOk()
            ->assertJsonPath('data.updated_count', 2)
            ->assertJsonPath('data.skipped_count', 1)
            ->assertJsonPath('data.failed_count', 0);

        $this->assertSame(KcaApplicationState::Reviewed, $first->refresh()->status);
        $this->assertSame(KcaApplicationState::Reviewed, $second->refresh()->status);
        $this->assertSame(KcaApplicationState::Reviewed, $alreadyReviewed->refresh()->status);
    }

    public function test_bulk_status_requires_reason_code_for_adverse_outcomes(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.applications.transition'], $scope);
        $this->authenticate($actor);

        $application = KcaApplication::factory()->reviewed()->create();

        $this->withHeaders($this->headers($scope))
            ->postJson('/api/v1/admin/kca/applications/bulk-transitions', [
                'application_ids' => [$application->public_id],
                'status' => 'not_accepted',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.fields.reason_code.0', 'The reason code field is required.');

        $this->withHeaders($this->headers($scope))
            ->postJson('/api/v1/admin/kca/applications/bulk-transitions', [
                'application_ids' => [$application->public_id],
                'status' => 'not_accepted',
                'reason_code' => 'does_not_meet_requirements',
            ])
            ->assertOk()
            ->assertJsonPath('data.updated_count', 1);

        $this->assertSame(KcaApplicationState::NotAccepted, $application->refresh()->status);
    }

    public function test_bulk_admit_requires_enrollment_permission(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.applications.transition'], $scope);
        $this->authenticate($actor);

        $cohort = KcaCohort::factory()->create();
        $application = KcaApplication::factory()->create();

        $this->withHeaders($this->headers($scope))
            ->postJson('/api/v1/admin/kca/applications/bulk-enrollments', [
                'application_ids' => [$application->public_id],
                'cohort_id' => $cohort->public_id,
                'starts_on' => now()->toDateString(),
            ])
            ->assertForbidden();
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
