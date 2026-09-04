<?php

namespace Tests\Feature;

use App\Kca\KcaAssignmentState;
use App\Models\KcaApplication;
use App\Models\KcaAssignment;
use App\Models\KcaChapter;
use App\Models\KcaCohort;
use App\Models\KcaEnrollment;
use App\Models\KcaLesson;
use App\Models\KcaMentorAssignment;
use App\Models\KcaModule;
use App\Models\KcaYear;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Kca\MapKcaModuleDaysAction;
use App\Support\Kca\PublishKcaModuleAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UserKcaChaptersActivityAndSoulTreeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_module_lesson_chapter_hierarchy_and_sequential_chapter_completion(): void
    {
        [$user, $enrollment, $module] = $this->activatedStudent();
        $this->authenticate($user);

        $lesson = KcaLesson::factory()->create([
            'kca_module_id' => $module->getKey(),
            'code' => 'L1',
            'sequence' => 1,
            'day_index' => 1,
            'body' => 'Lesson container',
        ]);
        $chapterOne = KcaChapter::factory()->create([
            'kca_lesson_id' => $lesson->getKey(),
            'code' => 'CH01',
            'title' => 'Chapter 1',
            'sequence' => 1,
            'body' => 'First chapter body',
        ]);
        $chapterTwo = KcaChapter::factory()->create([
            'kca_lesson_id' => $lesson->getKey(),
            'code' => 'CH02',
            'title' => 'Chapter 2',
            'sequence' => 2,
            'body' => 'Second chapter body',
        ]);
        $actor = User::factory()->create();
        $this->app->make(MapKcaModuleDaysAction::class)->handle($module, [1], $actor);
        $this->app->make(PublishKcaModuleAction::class)->handle($module->fresh(), $actor);

        $this->getJson("/api/v1/user/kca/modules/{$module->public_id}")
            ->assertOk()
            ->assertJsonPath('data.lessons.0.chapters.0.title', 'Chapter 1')
            ->assertJsonPath('data.lessons.0.chapters.1.title', 'Chapter 2');

        $this->getJson("/api/v1/user/kca/chapters/{$chapterTwo->public_id}")->assertForbidden();

        $this->postJson("/api/v1/user/kca/lessons/{$lesson->public_id}/complete", [
            'acknowledged' => true,
        ])->assertStatus(422);

        $this->postJson("/api/v1/user/kca/chapters/{$chapterOne->public_id}/complete", [
            'acknowledged' => true,
        ])->assertOk();

        $this->getJson("/api/v1/user/kca/chapters/{$chapterTwo->public_id}")
            ->assertOk()
            ->assertJsonPath('data.body', 'Second chapter body');

        $this->postJson("/api/v1/user/kca/chapters/{$chapterTwo->public_id}/complete", [
            'acknowledged' => true,
        ])->assertOk();

        $this->assertDatabaseHas('kca_lesson_progress', [
            'kca_enrollment_id' => $enrollment->getKey(),
            'kca_lesson_id' => $lesson->getKey(),
        ]);
    }

    public function test_dashboard_reports_bible_notes_devotionals_and_curriculum(): void
    {
        [$user] = $this->activatedStudent();
        $this->authenticate($user);

        $this->postJson('/api/v1/user/kca/notes', [
            'title' => 'Study note',
            'body' => 'The Word became flesh.',
        ])->assertCreated();

        $this->postJson('/api/v1/user/kca/devotionals', [
            'title' => 'Morning watch',
            'source' => 'Press',
            'reflection' => 'Grateful.',
        ])->assertCreated();

        $this->getJson('/api/v1/user/kca/dashboard')
            ->assertOk()
            ->assertJsonPath('data.enrolled', true)
            ->assertJsonPath('data.activity.notes.count', 1)
            ->assertJsonPath('data.activity.devotionals.count', 1)
            ->assertJsonStructure(['data' => ['activity' => ['bible', 'curriculum', 'assignments']]]);
    }

    public function test_mentor_can_read_mentee_activity_report(): void
    {
        [$student] = $this->activatedStudent();
        $mentorUser = User::factory()->withPerson()->create();
        $this->assertNotNull($mentorUser->person);
        $enrollment = KcaEnrollment::query()->where('person_id', $student->person_id)->firstOrFail();
        KcaMentorAssignment::factory()->create([
            'kca_enrollment_id' => $enrollment->getKey(),
            'mentor_person_id' => $mentorUser->person->getKey(),
            'assigned_by_user_id' => $mentorUser->getKey(),
        ]);

        $this->authenticate($student);
        $this->postJson('/api/v1/user/kca/notes', [
            'title' => 'Mentee note',
            'body' => 'Identity in Christ',
        ])->assertCreated();

        $this->authenticate($mentorUser);
        $this->getJson('/api/v1/user/kca/mentees')
            ->assertOk()
            ->assertJsonPath('data.0.enrollment_id', $enrollment->public_id)
            ->assertJsonPath('data.0.notes_count', 1);

        $this->getJson("/api/v1/user/kca/mentees/{$enrollment->public_id}")
            ->assertOk()
            ->assertJsonPath('data.notes.count', 1);
    }

    public function test_soul_winning_assignment_stays_open_until_tree_is_complete(): void
    {
        [$user, $enrollment, $module] = $this->activatedStudent();
        $this->authenticate($user);
        $module->forceFill(['published_at' => now(), 'is_active' => true])->save();

        $assignment = KcaAssignment::factory()->inState(KcaAssignmentState::Assigned)->create([
            'kca_enrollment_id' => $enrollment->getKey(),
            'kca_module_id' => $module->getKey(),
            'title' => 'Win souls',
            'assignment_kind' => 'soul_winning',
            'soul_tree_spec' => ['levels' => [1, 1]],
        ]);

        $this->postJson("/api/v1/user/kca/assignments/{$assignment->public_id}/souls", [
            'given_name' => 'Ada',
            'family_name' => 'Okeke',
        ])->assertCreated()->assertJsonPath('data.soul_tree.complete', false);

        $shown = $this->getJson("/api/v1/user/kca/assignments/{$assignment->public_id}")
            ->assertOk()
            ->assertJsonPath('data.soul_tree.open', true)
            ->assertJsonPath('data.assignment_kind', 'soul_winning')
            ->assertJsonPath('data.requires_media', true)
            ->assertJsonPath('data.module.id', $module->public_id)
            ->assertJsonStructure(['data' => ['lesson' => ['id', 'title'], 'evidence']]);
        $parentId = $shown->json('data.soul_tree.tree.0.id');
        $this->assertIsString($parentId);
        $this->assertNotNull($assignment->fresh()->kca_lesson_id);

        $this->postJson("/api/v1/user/kca/assignments/{$assignment->public_id}/souls", [
            'parent_id' => $parentId,
            'given_name' => 'Chidi',
        ])->assertCreated()->assertJsonPath('data.soul_tree.complete', true);

        $this->getJson("/api/v1/user/kca/assignments/{$assignment->public_id}")
            ->assertOk()
            ->assertJsonPath('data.soul_tree.open', false);
    }

    /**
     * @return array{0: User, 1: KcaEnrollment, 2: KcaModule}
     */
    private function activatedStudent(): array
    {
        $user = User::factory()->withPerson()->create();
        $person = $user->person;
        $this->assertNotNull($person);
        $application = KcaApplication::factory()->accepted()->for($person)->create();
        $year = KcaYear::factory()->create();
        $cohort = KcaCohort::factory()->for($year, 'year')->create(['timezone' => 'UTC']);
        $enrollment = KcaEnrollment::factory()
            ->for($application, 'application')
            ->for($person)
            ->for($year, 'year')
            ->for($cohort, 'cohort')
            ->create(['starts_on' => now()->toDateString()]);
        $module = KcaModule::factory()->create(['duration_days' => 1, 'sequence' => 1]);

        return [$user, $enrollment, $module];
    }

    private function authenticate(User $user): void
    {
        $session = SecuritySession::factory()->for($user)->create();
        $this->actingAs($user)->withSession([
            'security_session_id' => $session->public_id,
            'auth.mfa_verified_at' => now()->utc()->toIso8601String(),
        ]);
    }
}
