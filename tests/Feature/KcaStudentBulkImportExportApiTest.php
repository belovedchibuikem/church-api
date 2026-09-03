<?php

namespace Tests\Feature;

use App\Models\Church;
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
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class KcaStudentBulkImportExportApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_operator_can_download_import_template(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.enrollments.manage'], $scope);
        $this->authenticate($actor);

        $response = $this->withHeaders($this->headers($scope))
            ->get('/api/v1/admin/kca/students/import-template')
            ->assertOk();

        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $csv = $response->streamedContent();
        $this->assertStringContainsString('given_name', $csv);
        $this->assertStringContainsString('recommender_email', $csv);
        $this->assertStringContainsString('cohort_code', $csv);
        $this->assertStringContainsString('Ada', $csv);
    }

    public function test_operator_can_import_and_enroll_students_from_csv(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.enrollments.manage'], $scope);
        $this->authenticate($actor);

        $church = Church::factory()->create(['name' => 'Bulk Import Chapel']);
        $year = KcaYear::factory()->create();
        $cohort = KcaCohort::factory()->for($year, 'year')->create([
            'code' => 'KCA-BULK-A',
            'name' => 'Bulk Cohort A',
        ]);

        $csv = $this->csv([
            [
                'given_name' => 'Ngozi',
                'family_name' => 'Adeyemi',
                'email' => 'ngozi.adeyemi@example.org',
                'phone' => '+2348011111111',
                'create_login' => '',
                'password' => '',
                'person_id' => '',
                'fullName' => 'Ngozi Adeyemi',
                'church_id' => $church->public_id,
                'church_name' => '',
                'home_church_id' => '',
                'home_church_name' => '',
                'pastor_id' => '',
                'pastor_email' => '',
                'years' => '1–3 years',
                'baptised' => 'Yes',
                'story' => 'Walking with Christ since baptism.',
                'why' => 'To serve in youth ministry.',
                'interest' => 'Youth',
                'interest2' => 'Discipleship',
                'attendance_commitment' => 'yes',
                'conduct_commitment' => 'yes',
                'communication_commitment' => 'yes',
                'declaration_signature' => 'Ngozi Adeyemi',
                'declaration_date' => now()->toDateString(),
                'declaration_confirmed' => 'yes',
                'guardian_name' => '',
                'guardian_relationship' => '',
                'guardian_phone' => '',
                'guardian_email' => '',
                'guardian_consent' => '',
                'recommender_name' => 'Pastor Grace',
                'recommender_position' => 'Lead Pastor',
                'recommender_phone' => '+2348022222222',
                'recommender_email' => 'pastor.grace@example.org',
                'cohort_id' => '',
                'cohort_code' => $cohort->code,
                'registration_number' => '',
                'starts_on' => now()->toDateString(),
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('kca-students.csv', $csv);

        $this->withHeaders($this->headers($scope))
            ->post('/api/v1/admin/kca/students/import', [
                'file' => $file,
                'mode' => 'enroll',
            ])
            ->assertCreated()
            ->assertJsonPath('data.imported_count', 1)
            ->assertJsonPath('data.failed_count', 0)
            ->assertJsonPath('data.imported.0.application_status', 'accepted')
            ->assertJsonPath('data.imported.0.enrollment.cohort_id', $cohort->public_id);

        $this->assertSame(1, KcaEnrollment::query()->count());
        $this->assertDatabaseHas('kca_applications', [
            'status' => 'accepted',
        ]);
    }

    public function test_import_reports_row_errors_without_aborting_valid_rows(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.enrollments.manage'], $scope);
        $this->authenticate($actor);

        $church = Church::factory()->create(['name' => 'Partial Import Chapel']);
        $year = KcaYear::factory()->create();
        $cohort = KcaCohort::factory()->for($year, 'year')->create(['code' => 'KCA-PARTIAL']);

        $good = [
            'given_name' => 'Good',
            'family_name' => 'Student',
            'email' => 'good.student@example.org',
            'phone' => '',
            'create_login' => '',
            'password' => '',
            'person_id' => '',
            'fullName' => 'Good Student',
            'church_id' => $church->public_id,
            'church_name' => '',
            'home_church_id' => '',
            'home_church_name' => '',
            'pastor_id' => '',
            'pastor_email' => '',
            'years' => '5+ years',
            'baptised' => 'Yes',
            'story' => 'Long walk with Christ.',
            'why' => 'To deepen ministry skills.',
            'interest' => 'Missions',
            'interest2' => '',
            'attendance_commitment' => 'yes',
            'conduct_commitment' => 'yes',
            'communication_commitment' => 'yes',
            'declaration_signature' => 'Good Student',
            'declaration_date' => now()->toDateString(),
            'declaration_confirmed' => 'yes',
            'guardian_name' => '',
            'guardian_relationship' => '',
            'guardian_phone' => '',
            'guardian_email' => '',
            'guardian_consent' => '',
            'recommender_name' => 'Pastor Paul',
            'recommender_position' => 'Elder',
            'recommender_phone' => '',
            'recommender_email' => 'pastor.paul@example.org',
            'cohort_id' => $cohort->public_id,
            'cohort_code' => '',
            'registration_number' => '',
            'starts_on' => now()->toDateString(),
        ];
        $bad = $good;
        $bad['given_name'] = 'Bad';
        $bad['family_name'] = 'Student';
        $bad['email'] = 'bad.student@example.org';
        $bad['fullName'] = 'Bad Student';
        $bad['declaration_signature'] = 'Bad Student';
        $bad['cohort_id'] = '';
        $bad['cohort_code'] = '';

        $file = UploadedFile::fake()->createWithContent('kca-students.csv', $this->csv([$bad, $good]));

        $this->withHeaders($this->headers($scope))
            ->post('/api/v1/admin/kca/students/import', [
                'file' => $file,
                'mode' => 'enroll',
            ])
            ->assertCreated()
            ->assertJsonPath('data.imported_count', 1)
            ->assertJsonPath('data.failed_count', 1)
            ->assertJsonPath('data.failures.0.row', 2);

        $this->assertSame(1, KcaEnrollment::query()->count());
    }

    public function test_operator_can_export_enrolled_students(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.enrollments.manage'], $scope);
        $this->authenticate($actor);

        $church = Church::factory()->create(['name' => 'Export Chapel']);
        $year = KcaYear::factory()->create();
        $cohort = KcaCohort::factory()->for($year, 'year')->create(['code' => 'KCA-EXPORT']);

        $csv = $this->csv([[
            'given_name' => 'Export',
            'family_name' => 'Me',
            'email' => 'export.me@example.org',
            'phone' => '',
            'create_login' => '',
            'password' => '',
            'person_id' => '',
            'fullName' => 'Export Me',
            'church_id' => $church->public_id,
            'church_name' => '',
            'home_church_id' => '',
            'home_church_name' => '',
            'pastor_id' => '',
            'pastor_email' => '',
            'years' => 'Less than 1',
            'baptised' => 'Preparing',
            'story' => 'New believer growing steadily.',
            'why' => 'To learn foundations.',
            'interest' => 'Evangelism',
            'interest2' => '',
            'attendance_commitment' => 'yes',
            'conduct_commitment' => 'yes',
            'communication_commitment' => 'yes',
            'declaration_signature' => 'Export Me',
            'declaration_date' => now()->toDateString(),
            'declaration_confirmed' => 'yes',
            'guardian_name' => '',
            'guardian_relationship' => '',
            'guardian_phone' => '',
            'guardian_email' => '',
            'guardian_consent' => '',
            'recommender_name' => 'Pastor Ruth',
            'recommender_position' => 'Pastor',
            'recommender_phone' => '',
            'recommender_email' => 'pastor.ruth@example.org',
            'cohort_id' => $cohort->public_id,
            'cohort_code' => '',
            'registration_number' => 'KCA-TEST-001',
            'starts_on' => now()->toDateString(),
        ]]);

        $this->withHeaders($this->headers($scope))
            ->post('/api/v1/admin/kca/students/import', [
                'file' => UploadedFile::fake()->createWithContent('seed.csv', $csv),
                'mode' => 'enroll',
            ])
            ->assertCreated();

        $response = $this->withHeaders($this->headers($scope))
            ->get('/api/v1/admin/kca/students/export')
            ->assertOk();

        $exported = $response->streamedContent();
        $this->assertStringContainsString('export.me@example.org', $exported);
        $this->assertStringContainsString('KCA-TEST-001', $exported);
        $this->assertStringContainsString($cohort->code, $exported);
        $this->assertSame(1, KcaApplication::query()->count());
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function csv(array $rows): string
    {
        $headers = array_keys($rows[0]);
        $lines = [implode(',', $headers)];
        foreach ($rows as $row) {
            $cells = [];
            foreach ($headers as $header) {
                $value = (string) ($row[$header] ?? '');
                $cells[] = '"'.str_replace('"', '""', $value).'"';
            }
            $lines[] = implode(',', $cells);
        }

        return implode("\n", $lines)."\n";
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
