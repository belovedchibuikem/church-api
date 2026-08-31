<?php

namespace Tests\Feature;

use App\Files\FileAssetClassification;
use App\Kca\KcaAssignmentState;
use App\Models\FileAsset;
use App\Models\KcaApplication;
use App\Models\KcaAssignment;
use App\Models\KcaCertificate;
use App\Models\KcaCohort;
use App\Models\KcaEnrollment;
use App\Models\KcaLeadershipRecommendation;
use App\Models\KcaLesson;
use App\Models\KcaModule;
use App\Models\KcaYear;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Kca\KcaLessonUnlockToken;
use App\Support\Kca\MapKcaModuleDaysAction;
use App\Support\Kca\PublishKcaModuleAction;
use App\Support\Kca\RequestKcaLeadershipRecommendationAction;
use App\Support\Kca\VerifyKcaLeadershipRecommendationAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UserKcaLessonEvidenceCertificateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_lesson_payload_issues_unlock_token_and_token_bypasses_live_day_lock(): void
    {
        [$user, $enrollment, $module] = $this->activatedStudent();
        $this->authenticate($user);

        $dayOne = KcaLesson::factory()->create([
            'kca_module_id' => $module->getKey(),
            'code' => 'L1',
            'sequence' => 1,
            'day_index' => 1,
            'body' => 'Day one body',
        ]);
        $dayTwo = KcaLesson::factory()->create([
            'kca_module_id' => $module->getKey(),
            'code' => 'L2',
            'sequence' => 2,
            'day_index' => 2,
            'body' => 'Day two body',
        ]);
        $actor = User::factory()->create();
        $this->app->make(MapKcaModuleDaysAction::class)->handle($module, null, $actor);
        $this->app->make(PublishKcaModuleAction::class)->handle($module->fresh(), $actor);

        $shown = $this->getJson("/api/v1/user/kca/lessons/{$dayOne->public_id}")
            ->assertOk()
            ->assertJsonPath('data.body', 'Day one body');
        $token = $shown->json('data.unlock_token');
        $this->assertIsString($token);
        $this->assertSame(64, strlen($token));

        $this->getJson("/api/v1/user/kca/lessons/{$dayTwo->public_id}")->assertForbidden();
        $this->postJson("/api/v1/user/kca/lessons/{$dayTwo->public_id}/complete", [
            'acknowledged' => true,
        ])->assertForbidden();

        $forged = $this->app->make(KcaLessonUnlockToken::class)->issue($enrollment, $dayTwo->fresh());
        $this->postJson("/api/v1/user/kca/lessons/{$dayTwo->public_id}/complete", [
            'acknowledged' => true,
            'unlock_token' => $forged,
            'idempotency_key' => 'offline-day2',
        ])->assertOk();

        $this->postJson("/api/v1/user/kca/lessons/{$dayTwo->public_id}/complete", [
            'acknowledged' => true,
            'idempotency_key' => 'offline-day2',
        ])->assertOk();
    }

    public function test_member_can_submit_assignment_evidence(): void
    {
        [$user, $enrollment] = $this->activatedStudent();
        $this->authenticate($user);
        $assignment = KcaAssignment::factory()
            ->for($enrollment, 'enrollment')
            ->inState(KcaAssignmentState::Assigned)
            ->create();
        $file = FileAsset::factory()
            ->available()
            ->for($user->person, 'owner')
            ->create([
                'purpose' => 'kca.evidence',
                'classification' => FileAssetClassification::Confidential,
            ]);

        $this->postJson("/api/v1/user/kca/assignments/{$assignment->public_id}/evidence", [
            'file_asset_id' => $file->public_id,
            'idempotency_key' => 'member-evidence-1',
        ])->assertCreated()->assertJsonPath('data.assignment_id', $assignment->public_id);
    }

    public function test_certificate_pdf_download_and_leadership_recommendation_workflow(): void
    {
        [$user, $enrollment] = $this->activatedStudent();
        $this->authenticate($user);
        KcaCertificate::factory()->for($enrollment, 'enrollment')->for($user->person, 'person')->create();

        $download = $this->get('/api/v1/user/kca/certificates/current/download');
        $download->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $download->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $download->getContent());

        $applicant = User::factory()->withPerson()->create();
        $this->authenticate($applicant);
        $this->postJson('/api/v1/user/kca/applications', [
            'application_data' => [
                'motivation' => 'Called',
                'recommendation' => 'Applicant tried to self-approve',
                'approved' => 'yes',
            ],
            'finalize' => false,
        ])->assertSuccessful();

        $application = KcaApplication::query()->where('person_id', $applicant->person_id)->latest('id')->first();
        $this->assertNotNull($application);
        $this->assertArrayNotHasKey('recommendation', $application->application_data ?? []);
        $this->assertArrayNotHasKey('approved', $application->application_data ?? []);

        $this->assertNull(KcaLeadershipRecommendation::query()->where('kca_application_id', $application->getKey())->first());

        $requested = $this->app->make(RequestKcaLeadershipRecommendationAction::class)->handle(
            $application,
            'Pastor Ada',
            'pastor.ada@example.com',
        );
        $this->assertNotNull($requested['token']);
        $row = KcaLeadershipRecommendation::query()->where('kca_application_id', $application->getKey())->first();
        $this->assertNotNull($row);
        $this->postJson('/api/v1/kca/recommendations/'.$requested['token'], [
            'statement' => 'This applicant is faithful and ready for formation.',
        ])->assertOk()->assertJsonPath('data.status', 'submitted');

        $row->refresh();
        $this->app->make(VerifyKcaLeadershipRecommendationAction::class)->handle($row, User::factory()->create());
        $this->authenticate($applicant);
        $this->getJson('/api/v1/user/kca/me')
            ->assertOk()
            ->assertJsonPath('data.application.recommendation.status', 'verified');
    }

    /** @return array{0: User, 1: KcaEnrollment, 2: KcaModule} */
    private function activatedStudent(): array
    {
        $user = User::factory()->withPerson()->create();
        $person = $user->person;
        $application = KcaApplication::factory()->accepted()->for($person)->create();
        $year = KcaYear::factory()->create();
        $cohort = KcaCohort::factory()->for($year, 'year')->create(['timezone' => 'UTC']);
        $enrollment = KcaEnrollment::factory()
            ->for($application, 'application')
            ->for($person)
            ->for($year, 'year')
            ->for($cohort, 'cohort')
            ->create(['starts_on' => now()->toDateString()]);
        $module = KcaModule::factory()->create(['duration_days' => 2, 'sequence' => 1]);

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
