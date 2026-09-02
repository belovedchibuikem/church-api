<?php

namespace Tests\Feature;

use App\AdvisoryAi\Assistant;
use App\AdvisoryAi\UseCase;
use App\Communication\CommunicationChannel;
use App\Files\FileAssetStatus;
use App\Models\Church;
use App\Models\FileAsset;
use App\Models\KcaApplication;
use App\Models\KcaCohort;
use App\Models\KcaEnrollment;
use App\Models\KcaYear;
use App\Models\MinistryEvent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\AuthorizationBundleCatalog;
use App\Support\Authorization\GrantPermissionToRoleAction;
use App\Support\Authorization\ScopeReference;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminRemainingGapsApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_platform_search_finds_a_created_church_by_name(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['platform.search.query'], $scope);
        $this->authenticate($actor);
        $church = Church::factory()->create(['name' => 'Unique Searchable Chapel']);

        $this->withHeaders($this->headers($scope))
            ->postJson('/api/v1/admin/platform/search/queries', [
                'term' => 'Searchable Chapel',
                'resource_types' => ['church'],
            ])
            ->assertOk()
            ->assertJsonPath('data.0.resource_type', 'church')
            ->assertJsonPath('data.0.resource_id', $church->public_id)
            ->assertJsonPath('data.0.title', 'Unique Searchable Chapel')
            ->assertJsonPath('data.0.classification', 'public');
    }

    public function test_advisory_returns_unavailable_when_provider_is_disabled(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['platform.advisory.request'], $scope);
        $this->authenticate($actor);

        $this->withHeaders($this->headers($scope))
            ->postJson('/api/v1/admin/platform/advisory/requests', [
                'assistant' => Assistant::Mission->value,
                'use_case' => UseCase::FollowUpGapDetection->value,
                'instruction' => 'Find follow-up gaps.',
            ])
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.reason_code', 'provider_disabled')
            ->assertJsonPath('data.requires_human_decision', true);
    }

    public function test_kca_operator_can_create_a_year(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.years.manage'], $scope);
        $this->authenticate($actor);

        $this->withHeaders($this->headers($scope))
            ->postJson('/api/v1/admin/kca/years', [
                'code' => 'year-2026-gaps',
                'name' => '2026 KCA Year',
                'starts_on' => '2026-01-01',
                'ends_on' => '2026-12-31',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'year-2026-gaps')
            ->assertJsonPath('data.name', '2026 KCA Year');
    }

    public function test_kca_operator_can_update_a_cohort(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.cohorts.manage'], $scope);
        $this->authenticate($actor);
        $year = KcaYear::factory()->create(['name' => 'KCA 2026']);
        $cohort = KcaCohort::factory()->for($year, 'year')->create([
            'code' => 'KCA001',
            'name' => '1st Batch 2026',
            'timezone' => 'Africa/Lagos',
        ]);

        $this->withHeaders($this->headers($scope))
            ->patchJson("/api/v1/admin/kca/cohorts/{$cohort->public_id}", [
                'code' => 'KCA001',
                'name' => '1st Batch 2026 Updated',
                'starts_on' => $cohort->starts_on?->toDateString(),
                'ends_on' => $cohort->ends_on?->toDateString(),
                'timezone' => 'Africa/Lagos',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', '1st Batch 2026 Updated')
            ->assertJsonPath('data.year_name', 'KCA 2026')
            ->assertJsonPath('data.timezone', 'Africa/Lagos');
    }

    public function test_kca_operator_can_update_a_lesson(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.lessons.manage'], $scope);
        $this->authenticate($actor);
        $module = \App\Models\KcaModule::factory()->create(['title' => 'Identity in Christ']);
        $lesson = \App\Models\KcaLesson::factory()->for($module, 'module')->create([
            'code' => 'L01',
            'title' => 'Who Am I?',
            'sequence' => 1,
            'body' => 'Original body',
        ]);

        $this->withHeaders($this->headers($scope))
            ->patchJson("/api/v1/admin/kca/lessons/{$lesson->public_id}", [
                'code' => 'L01',
                'title' => 'Who Am I in Christ?',
                'sequence' => 1,
                'lesson_type' => 'text',
                'summary' => 'Updated summary',
                'body' => 'Updated lesson body.',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Who Am I in Christ?')
            ->assertJsonPath('data.summary', 'Updated summary');
    }

    public function test_kca_operator_can_update_lesson_in_published_module(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.lessons.manage'], $scope);
        $this->authenticate($actor);
        $module = \App\Models\KcaModule::factory()->create([
            'title' => 'Identity in Christ',
            'published_at' => now(),
        ]);
        $lesson = \App\Models\KcaLesson::factory()->for($module, 'module')->create([
            'code' => 'L01',
            'title' => 'Who Am I?',
            'sequence' => 1,
            'body' => 'Original body',
            'day_index' => 1,
        ]);

        $this->withHeaders($this->headers($scope))
            ->patchJson("/api/v1/admin/kca/lessons/{$lesson->public_id}", [
                'code' => 'L01',
                'title' => 'Who Am I in Christ?',
                'sequence' => 1,
                'lesson_type' => 'text',
                'summary' => 'Updated after publish',
                'body' => 'Updated lesson body.',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Who Am I in Christ?')
            ->assertJsonPath('data.summary', 'Updated after publish');
    }

    public function test_kca_operator_can_remap_days_on_published_module(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.modules.manage'], $scope);
        $this->authenticate($actor);
        $module = \App\Models\KcaModule::factory()->create([
            'title' => 'Identity in Christ',
            'duration_days' => 7,
            'published_at' => now(),
        ]);
        \App\Models\KcaLesson::factory()->for($module, 'module')->create([
            'code' => 'L01',
            'title' => 'Lesson one',
            'sequence' => 1,
            'day_index' => 1,
        ]);
        \App\Models\KcaLesson::factory()->for($module, 'module')->create([
            'code' => 'L02',
            'title' => 'Lesson two',
            'sequence' => 2,
            'day_index' => 1,
        ]);
        \App\Models\KcaLesson::factory()->for($module, 'module')->create([
            'code' => 'L03',
            'title' => 'Lesson three',
            'sequence' => 3,
            'day_index' => null,
        ]);

        $this->withHeaders($this->headers($scope))
            ->postJson("/api/v1/admin/kca/modules/{$module->public_id}/day-map", [])
            ->assertOk();

        $dayIndexes = \App\Models\KcaLesson::query()
            ->where('kca_module_id', $module->getKey())
            ->orderBy('sequence')
            ->pluck('day_index')
            ->map(fn ($day) => (int) $day)
            ->all();

        $this->assertSame([1, 2, 3], $dayIndexes);
    }

    public function test_kca_operator_can_add_multiple_lessons_to_a_module(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.lessons.manage'], $scope);
        $this->authenticate($actor);
        $module = \App\Models\KcaModule::factory()->create(['title' => 'Identity in Christ']);

        $this->withHeaders($this->headers($scope))
            ->postJson("/api/v1/admin/kca/modules/{$module->public_id}/lessons", [
                'code' => 'L01',
                'title' => 'Lesson one',
                'sequence' => 1,
                'lesson_type' => 'text',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'L01')
            ->assertJsonPath('data.sequence', 1);

        $this->withHeaders($this->headers($scope))
            ->postJson("/api/v1/admin/kca/modules/{$module->public_id}/lessons", [
                'code' => 'L02',
                'title' => 'Lesson two',
                'sequence' => 2,
                'lesson_type' => 'text',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'L02')
            ->assertJsonPath('data.sequence', 2);
    }

    public function test_kca_operator_can_add_lesson_to_published_module(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.lessons.manage'], $scope);
        $this->authenticate($actor);
        $module = \App\Models\KcaModule::factory()->create([
            'title' => 'Identity in Christ',
            'published_at' => now(),
        ]);

        $this->withHeaders($this->headers($scope))
            ->postJson("/api/v1/admin/kca/modules/{$module->public_id}/lessons", [
                'code' => 'L03',
                'title' => 'New lesson after publish',
                'sequence' => 1,
                'day_index' => 1,
                'lesson_type' => 'text',
                'body' => 'Added while module is published.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'L03')
            ->assertJsonPath('data.title', 'New lesson after publish');
    }

    public function test_kca_lesson_creation_returns_duplicate_sequence_message(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.lessons.manage'], $scope);
        $this->authenticate($actor);
        $module = \App\Models\KcaModule::factory()->create(['title' => 'Identity in Christ']);
        \App\Models\KcaLesson::factory()->for($module, 'module')->create([
            'code' => 'L01',
            'title' => 'Lesson one',
            'sequence' => 1,
        ]);

        $this->withHeaders($this->headers($scope))
            ->postJson("/api/v1/admin/kca/modules/{$module->public_id}/lessons", [
                'code' => 'L02',
                'title' => 'Lesson two',
                'sequence' => 1,
                'lesson_type' => 'text',
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.message',
                'A KCA lesson with this code or sequence already exists for the module.',
            );
    }

    public function test_kca_operator_can_register_a_student_application_on_behalf_of_applicant(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.applications.transition'], $scope);
        $this->authenticate($actor);

        $this->withHeaders($this->headers($scope))
            ->postJson('/api/v1/admin/kca/applications', [
                'given_name' => 'Ada',
                'family_name' => 'Okafor',
                'email' => 'ada.okafor@example.org',
                'phone' => '+2348012345678',
                'finalize' => true,
                'application_data' => [
                    'fullName' => 'Ada Okafor',
                    'email' => 'ada.okafor@example.org',
                    'why' => 'To grow in ministry leadership.',
                    'recommender_name' => 'Pastor Daniel',
                    'recommender_email' => 'pastor.daniel@example.org',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'received');

        $this->assertDatabaseHas('kca_applications', [
            'status' => 'received',
        ]);
        $this->assertSame(1, KcaApplication::query()->count());
    }

    public function test_kca_operator_can_save_and_resume_a_draft_application(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.applications.transition'], $scope);
        $this->authenticate($actor);

        $draftResponse = $this->withHeaders($this->headers($scope))
            ->postJson('/api/v1/admin/kca/applications', [
                'given_name' => 'Draft',
                'family_name' => 'Student',
                'finalize' => false,
                'application_data' => [
                    'fullName' => 'Draft Student',
                    'church_id' => 'placeholder',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');

        $applicationId = $draftResponse->json('data.id');
        $this->assertIsString($applicationId);

        $this->withHeaders($this->headers($scope))
            ->postJson('/api/v1/admin/kca/applications', [
                'application_id' => $applicationId,
                'finalize' => false,
                'application_data' => [
                    'fullName' => 'Draft Student',
                    'church_id' => 'placeholder',
                    'why' => 'Saved for later completion.',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.id', $applicationId);

        $this->assertDatabaseHas('kca_applications', [
            'public_id' => $applicationId,
            'status' => 'draft',
        ]);
    }

    public function test_kca_operator_can_register_student_with_login_account(): void
    {
        Role::query()->firstOrCreate(
            ['code' => AuthorizationBundleCatalog::MEMBER_SECURITY_ROLE],
            ['name' => 'Member self service'],
        );
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.applications.transition'], $scope);
        $this->authenticate($actor);

        $this->withHeaders($this->headers($scope))
            ->postJson('/api/v1/admin/kca/applications', [
                'given_name' => 'Chidi',
                'family_name' => 'Nwosu',
                'email' => 'chidi.nwosu@example.org',
                'create_login' => true,
                'password' => 'StudentPass123',
                'password_confirmation' => 'StudentPass123',
                'finalize' => true,
                'application_data' => [
                    'fullName' => 'Chidi Nwosu',
                    'email' => 'chidi.nwosu@example.org',
                    'why' => 'To serve in media ministry.',
                    'recommender_name' => 'Pastor Grace',
                    'recommender_email' => 'pastor.grace@example.org',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'received');

        $this->assertDatabaseHas('users', [
            'email' => 'chidi.nwosu@example.org',
        ]);
        $this->assertDatabaseHas('kca_applications', [
            'status' => 'received',
        ]);
    }

    public function test_kca_operator_can_record_an_assessment_for_one_student(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.enrollments.manage'], $scope);
        $this->authenticate($actor);
        $enrollment = KcaEnrollment::factory()->create();

        $this->withHeaders($this->headers($scope))
            ->postJson('/api/v1/admin/kca/assessment-results', [
                'audience' => 'student',
                'kca_enrollment_id' => $enrollment->public_id,
                'assessment_code' => 'Identity final',
                'result_code' => 'pass',
                'score' => 88,
            ])
            ->assertCreated()
            ->assertJsonPath('data.recorded', 1)
            ->assertJsonPath('data.assessment_code', 'Identity final');
    }

    public function test_events_operator_can_create_an_event(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['events.events.manage'], $scope);
        $this->authenticate($actor);

        $this->withHeaders($this->headers($scope))
            ->postJson('/api/v1/admin/events', [
                'category_code' => 'training',
                'name' => 'Leaders Retreat',
                'starts_at' => now()->addWeek()->utc()->toIso8601String(),
                'ends_at' => now()->addWeek()->addDay()->utc()->toIso8601String(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Leaders Retreat')
            ->assertJsonPath('data.category_code', 'training');
    }

    public function test_events_operator_can_show_update_and_delete_an_event(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['events.events.view', 'events.events.manage'], $scope);
        $this->authenticate($actor);
        $event = MinistryEvent::factory()->create([
            'name' => 'Youth Summit',
            'category_code' => 'youth',
            'starts_at' => now()->addWeek()->utc(),
            'ends_at' => now()->addWeek()->addDay()->utc(),
        ]);

        $this->withHeaders($this->headers($scope))
            ->getJson("/api/v1/admin/events/{$event->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $event->public_id)
            ->assertJsonPath('data.name', 'Youth Summit')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.can_delete', true);

        $this->withHeaders($this->headers($scope))
            ->putJson("/api/v1/admin/events/{$event->public_id}", [
                'name' => 'Youth Summit Updated',
                'published_at' => now()->utc()->toIso8601String(),
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Youth Summit Updated')
            ->assertJsonPath('data.status', 'published');

        $this->withHeaders($this->headers($scope))
            ->deleteJson("/api/v1/admin/events/{$event->public_id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->withHeaders($this->headers($scope))
            ->getJson("/api/v1/admin/events/{$event->public_id}")
            ->assertNotFound();
    }

    public function test_kca_operator_can_create_show_update_and_delete_orientation_session(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.orientation.view', 'kca.orientation.manage'], $scope);
        $this->authenticate($actor);
        $cohort = KcaCohort::factory()->create();

        $create = $this->withHeaders($this->headers($scope))
            ->postJson('/api/v1/admin/kca/orientation-sessions', [
                'cohort_id' => $cohort->public_id,
                'name' => 'Batch Orientation Day 1',
                'starts_at' => now()->addWeek()->utc()->toIso8601String(),
                'ends_at' => now()->addWeek()->addHours(3)->utc()->toIso8601String(),
                'venue_label' => 'Main Auditorium',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Batch Orientation Day 1')
            ->assertJsonPath('data.cohort_id', $cohort->public_id);

        $sessionId = (string) $create->json('data.id');

        $this->withHeaders($this->headers($scope))
            ->getJson("/api/v1/admin/kca/orientation-sessions/{$sessionId}")
            ->assertOk()
            ->assertJsonPath('data.id', $sessionId)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.can_delete', true);

        $this->withHeaders($this->headers($scope))
            ->putJson("/api/v1/admin/kca/orientation-sessions/{$sessionId}", [
                'name' => 'Batch Orientation — Updated',
                'published_at' => now()->utc()->toIso8601String(),
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Batch Orientation — Updated')
            ->assertJsonPath('data.status', 'scheduled');

        $this->withHeaders($this->headers($scope))
            ->deleteJson("/api/v1/admin/kca/orientation-sessions/{$sessionId}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('kca_orientation_sessions', ['public_id' => $sessionId]);
    }

    public function test_platform_admin_can_approve_a_quarantined_file_asset(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['platform.files.approve'], $scope);
        $this->authenticate($actor);
        $asset = FileAsset::factory()->create(['status' => FileAssetStatus::Quarantined]);

        $this->withHeaders($this->headers($scope))
            ->postJson("/api/v1/admin/platform/files/{$asset->public_id}/approval")
            ->assertOk()
            ->assertJsonPath('data.id', $asset->public_id)
            ->assertJsonPath('data.status', FileAssetStatus::Available->value)
            ->assertJsonPath('data.malware_scan_status', 'clean');
    }

    public function test_platform_admin_can_download_available_file_content(): void
    {
        Storage::fake('local');
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['platform.files.view'], $scope);
        $this->authenticate($actor);
        $contents = 'admin visible document';
        $asset = FileAsset::factory()->available()->create([
            'detected_mime_type' => 'text/plain',
            'byte_size' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'metadata' => ['original_filename' => 'admin-notes.txt'],
        ]);
        Storage::disk('local')->put($asset->object_key, $contents);

        $response = $this->withHeaders($this->headers($scope))
            ->get("/api/v1/admin/platform/files/{$asset->public_id}/content");

        $response
            ->assertOk()
            ->assertDownload('admin-notes.txt');
        $this->assertSame($contents, $response->streamedContent());
    }

    public function test_communications_operator_can_create_a_template(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['communications.templates.manage'], $scope);
        $this->authenticate($actor);

        $this->withHeaders($this->headers($scope))
            ->postJson('/api/v1/admin/communications/templates', [
                'code' => 'welcome.member',
                'channel' => CommunicationChannel::Email->value,
                'locale' => 'en',
                'subject' => 'Welcome',
                'body' => 'Welcome to Family House.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'welcome.member')
            ->assertJsonPath('data.channel', 'email')
            ->assertJsonPath('data.subject', 'Welcome');
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
