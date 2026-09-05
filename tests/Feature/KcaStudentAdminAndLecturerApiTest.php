<?php

namespace Tests\Feature;

use App\Models\KcaApplication;
use App\Models\KcaCohort;
use App\Models\KcaEnrollment;
use App\Models\KcaLecturerAssignment;
use App\Models\KcaLesson;
use App\Models\KcaModule;
use App\Models\KcaYear;
use App\Models\Permission;
use App\Models\Person;
use App\Models\Role;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\GrantPermissionToRoleAction;
use App\Support\Authorization\ScopeReference;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class KcaStudentAdminAndLecturerApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_student_view_includes_registration_steps_and_contact_details(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.enrollments.view'], $scope);
        $this->authenticate($actor);

        $student = User::factory()->withPerson()->create(['email' => 'ada.okafor@example.org']);
        $person = $student->person;
        $this->assertNotNull($person);
        $person->profile?->forceFill([
            'given_name' => 'Ada',
            'family_name' => 'Okafor',
            'phone' => '+2348001112222',
        ])->save();

        $application = KcaApplication::factory()->accepted()->for($person)->create([
            'application_data' => [
                'fullName' => 'Ada Okafor',
                'email' => 'ada.okafor@example.org',
                'phone' => '+2348001112222',
                'years' => '3–5 years',
                'baptised' => 'Yes',
                'story' => 'Came to faith in university.',
                'why' => 'To serve in discipleship.',
                'interest' => 'Discipleship',
                'interest2' => 'Youth',
                'attendance_commitment' => true,
                'conduct_commitment' => true,
                'communication_commitment' => true,
                'declaration_signature' => 'Ada Okafor',
                'declaration_date' => '2026-09-05',
                'declaration_confirmed' => true,
                'guardian_name' => 'Ngozi Okafor',
                'guardian_relationship' => 'Mother',
                'guardian_phone' => '+2348003334444',
                'recommender_name' => 'Pastor Grace',
                'recommender_position' => 'Lead pastor',
            ],
        ]);
        $year = KcaYear::factory()->create();
        $cohort = KcaCohort::factory()->for($year, 'year')->create();
        $enrollment = KcaEnrollment::factory()
            ->for($application, 'application')
            ->for($person)
            ->for($year, 'year')
            ->for($cohort, 'cohort')
            ->create();

        $this->withHeaders($this->headers($scope))
            ->getJson("/api/v1/admin/kca/enrollments/{$enrollment->public_id}")
            ->assertOk()
            ->assertJsonPath('data.person_name', 'Ada Okafor')
            ->assertJsonPath('data.email', 'ada.okafor@example.org')
            ->assertJsonPath('data.phone', '+2348001112222')
            ->assertJsonPath('data.registration.declaration_signature', 'Ada Okafor')
            ->assertJsonPath('data.registration.why', 'To serve in discipleship.')
            ->assertJsonPath('data.registration.guardian_name', 'Ngozi Okafor')
            ->assertJsonPath('data.registration.recommender_name', 'Pastor Grace')
            ->assertJsonPath('data.registration_sections.0.title', 'Personal details');
    }

    public function test_operator_can_update_and_delete_a_student_enrollment(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.enrollments.manage', 'kca.enrollments.view'], $scope);
        $this->authenticate($actor);

        $enrollment = KcaEnrollment::factory()->create();
        $year = KcaYear::factory()->create();
        $cohort = KcaCohort::factory()->for($year, 'year')->create();

        $this->withHeaders($this->headers($scope))
            ->patchJson("/api/v1/admin/kca/enrollments/{$enrollment->public_id}", [
                'kca_cohort_id' => $cohort->public_id,
                'registration_number' => 'KCA-2026-99999',
            ])
            ->assertOk()
            ->assertJsonPath('data.cohort_id', $cohort->public_id)
            ->assertJsonPath('data.registration_number', 'KCA-2026-99999');

        $this->withHeaders($this->headers($scope))
            ->deleteJson("/api/v1/admin/kca/enrollments/{$enrollment->public_id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('kca_enrollments', ['id' => $enrollment->getKey()]);
    }

    public function test_operator_can_bind_a_lecturer_to_a_module_lesson(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.modules.manage'], $scope);
        $this->authenticate($actor);

        $module = KcaModule::factory()->create(['title' => 'Discipleship and Kingdom Growth']);
        $lesson = KcaLesson::factory()->for($module, 'module')->create(['title' => 'Lesson 1: The Call']);
        $cohort = KcaCohort::factory()->create();
        $lecturer = Person::factory()->withProfile()->create();
        $lecturer->profile?->forceFill([
            'given_name' => 'Pastor',
            'family_name' => 'BS',
        ])->save();

        $this->withHeaders($this->headers($scope))
            ->postJson('/api/v1/admin/kca/lecturer-assignments', [
                'lecturer_person_id' => $lecturer->public_id,
                'kca_module_id' => $module->public_id,
                'kca_lesson_id' => $lesson->public_id,
                'kca_cohort_id' => $cohort->public_id,
                'starts_at' => now()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.kca_module_id', $module->public_id)
            ->assertJsonPath('data.kca_lesson_id', $lesson->public_id)
            ->assertJsonPath('data.lesson_title', 'Lesson 1: The Call')
            ->assertJsonPath('data.lecturer_person_id', $lecturer->public_id);

        $this->assertDatabaseHas('kca_lecturer_assignments', [
            'kca_module_id' => $module->getKey(),
            'kca_lesson_id' => $lesson->getKey(),
            'lecturer_person_id' => $lecturer->getKey(),
        ]);
        $this->assertSame(1, KcaLecturerAssignment::query()->count());
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
