<?php

namespace Tests\Feature;

use App\Models\KcaApplication;
use App\Models\KcaCohort;
use App\Models\KcaEnrollment;
use App\Models\KcaLesson;
use App\Models\KcaModule;
use App\Models\KcaYear;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Kca\KcaDailyBundleMapper;
use App\Support\Kca\MapKcaModuleDaysAction;
use App\Support\Kca\PublishKcaModuleAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UserKcaMeAndPacingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_me_routes_unapplied_member_to_overview(): void
    {
        $user = User::factory()->withPerson()->create();
        $this->authenticate($user);

        $this->getJson('/api/v1/user/kca/me')
            ->assertOk()
            ->assertJsonPath('data.state', 'none')
            ->assertJsonPath('data.destination', 'overview');
    }

    public function test_draft_resume_and_submitted_progress_destinations(): void
    {
        $user = User::factory()->withPerson()->create();
        $this->authenticate($user);

        $this->postJson('/api/v1/user/kca/applications', [
            'application_data' => ['motivation' => 'Called'],
            'finalize' => false,
        ])->assertCreated()->assertJsonPath('data.status', 'draft');

        $this->getJson('/api/v1/user/kca/me')
            ->assertOk()
            ->assertJsonPath('data.destination', 'resume_application');

        $this->postJson('/api/v1/user/kca/applications', [
            'application_data' => ['motivation' => 'Called', 'church' => 'Lagos'],
            'finalize' => true,
        ])->assertOk()->assertJsonPath('data.status', 'received');

        $this->getJson('/api/v1/user/kca/me')
            ->assertOk()
            ->assertJsonPath('data.destination', 'admission_progress');

        $this->postJson('/api/v1/user/kca/applications', [
            'application_data' => ['motivation' => 'Changed after submit'],
            'finalize' => true,
        ])->assertStatus(409);
    }

    public function test_activated_student_is_routed_to_dashboard_and_cannot_finish_module_in_one_day(): void
    {
        $user = User::factory()->withPerson()->create();
        $person = $user->person;
        $this->assertNotNull($person);
        $this->authenticate($user);

        $application = KcaApplication::factory()->accepted()->for($person)->create();
        $year = KcaYear::factory()->create();
        $cohort = KcaCohort::factory()->for($year, 'year')->create(['timezone' => 'UTC']);
        KcaEnrollment::factory()
            ->for($application, 'application')
            ->for($person)
            ->for($year, 'year')
            ->for($cohort, 'cohort')
            ->create(['starts_on' => now()->toDateString()]);

        $this->getJson('/api/v1/user/kca/me')
            ->assertOk()
            ->assertJsonPath('data.destination', 'student_dashboard')
            ->assertJsonPath('data.state', 'activated_student');

        $module = KcaModule::factory()->create(['duration_days' => 7, 'sequence' => 1]);
        $lessons = [];
        for ($i = 1; $i <= 12; $i++) {
            $lessons[] = KcaLesson::factory()->create([
                'kca_module_id' => $module->getKey(),
                'code' => 'L'.$i,
                'sequence' => $i,
            ]);
        }
        $this->assertSame(
            [1, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 7],
            KcaDailyBundleMapper::evenDistribution(12, 7),
        );
        $this->assertSame(
            [1, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 6, 7, 7, 8, 8, 9, 10],
            KcaDailyBundleMapper::evenDistribution(18, 10),
        );

        $actor = User::factory()->create();
        $this->app->make(MapKcaModuleDaysAction::class)->handle($module, null, $actor);
        $this->app->make(PublishKcaModuleAction::class)->handle($module->fresh(), $actor);
        $module->refresh();
        $this->assertNotNull($module->published_at);

        $dayOne = KcaLesson::query()->where('kca_module_id', $module->getKey())->where('day_index', 1)->orderBy('sequence')->get();
        $this->assertGreaterThanOrEqual(1, $dayOne->count());
        foreach ($dayOne as $lesson) {
            $this->postJson("/api/v1/user/kca/lessons/{$lesson->public_id}/complete", [
                'acknowledged' => true,
                'idempotency_key' => 'day1-'.$lesson->public_id,
            ])->assertOk();
        }

        $dayTwo = KcaLesson::query()->where('kca_module_id', $module->getKey())->where('day_index', 2)->orderBy('sequence')->first();
        $this->assertNotNull($dayTwo);
        $this->postJson("/api/v1/user/kca/lessons/{$dayTwo->public_id}/complete", [
            'acknowledged' => true,
        ])->assertForbidden();

        Carbon::setTestNow(now()->addDay());
        try {
            $this->authenticate($user);
            $this->postJson("/api/v1/user/kca/lessons/{$dayTwo->public_id}/complete", [
                'acknowledged' => true,
            ])->assertOk();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_learning_modules_are_hidden_until_activation(): void
    {
        $user = User::factory()->withPerson()->create();
        $this->authenticate($user);
        $this->getJson('/api/v1/user/kca/modules')->assertNotFound();
        $this->getJson('/api/v1/user/kca/dashboard')
            ->assertOk()
            ->assertJsonPath('data.enrolled', false);
    }

    public function test_orientation_and_practical_service_are_api_payloads(): void
    {
        $user = User::factory()->withPerson()->create();
        $this->authenticate($user);

        $this->getJson('/api/v1/user/kca/orientation')
            ->assertOk()
            ->assertJsonPath('data.enrolled', false)
            ->assertJsonPath('data.stages.0.key', 'overview')
            ->assertJsonPath('data.stages.3.key', 'mentors');

        $this->getJson('/api/v1/user/kca/practical-service')
            ->assertOk()
            ->assertJsonPath('data.enrolled', false)
            ->assertJsonPath('data.departments_count', 0);
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
