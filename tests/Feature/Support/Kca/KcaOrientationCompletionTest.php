<?php

namespace Tests\Feature\Support\Kca;

use App\Kca\KcaApplicationState;
use App\Models\KcaApplication;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Kca\CompleteKcaOrientationAction;
use App\Support\Kca\RecordKcaOrientationStageAction;
use App\Support\Kca\TransitionKcaApplicationToStatusAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class KcaOrientationCompletionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_applicant_records_stages_and_completes_orientation(): void
    {
        $user = User::factory()->withPerson()->create();
        $person = $user->person;
        $this->assertNotNull($person);
        $application = KcaApplication::factory()->for($person)->interview()->create();

        $recordStage = $this->app->make(RecordKcaOrientationStageAction::class);
        foreach (['overview', 'rules', 'path', 'mentors'] as $stage) {
            $recordStage->handle($person, $stage);
        }

        $completed = $this->app->make(CompleteKcaOrientationAction::class)
            ->handleForApplicant($person, $user);

        $this->assertSame(KcaApplicationState::Reviewed, $completed->status);
        $this->assertNotNull($completed->orientation_completed_at);
        $this->assertSame($application->getKey(), $completed->getKey());
    }

    public function test_admin_can_force_complete_orientation(): void
    {
        $actor = User::factory()->create();
        $person = \App\Models\Person::factory()->create();
        $application = KcaApplication::factory()->for($person)->interview()->create();

        $completed = $this->app->make(CompleteKcaOrientationAction::class)
            ->handleForAdmin($application, $actor);

        $this->assertSame(KcaApplicationState::Reviewed, $completed->status);
        $this->assertNotNull($completed->orientation_completed_at);
    }

    public function test_admin_admit_from_received_chains_through_reviewed(): void
    {
        $actor = User::factory()->create();
        $application = KcaApplication::factory()->create(['status' => KcaApplicationState::Received]);

        $updated = $this->app->make(TransitionKcaApplicationToStatusAction::class)
            ->handle($application, KcaApplicationState::Accepted, $actor, 'accepted_by_admin');

        $this->assertSame(KcaApplicationState::Accepted, $updated->status);
    }

    public function test_applicant_can_complete_orientation_via_api(): void
    {
        $user = User::factory()->withPerson()->create();
        $person = $user->person;
        $this->assertNotNull($person);
        KcaApplication::factory()->for($person)->interview()->create();
        $this->authenticate($user);

        foreach (['overview', 'rules', 'path', 'mentors'] as $stage) {
            $this->postJson("/api/v1/user/kca/orientation/stages/{$stage}/complete")->assertOk();
        }

        $this->postJson('/api/v1/user/kca/orientation/complete')
            ->assertOk()
            ->assertJsonPath('data.status', 'reviewed');
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
}
