<?php

namespace Tests\Feature;

use App\Kca\KcaApplicationState;
use App\Models\SecuritySession;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UserKcaApplicationCurrentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_stores_draft_and_finalizes_application(): void
    {
        $user = User::factory()->withPerson()->create();
        $this->authenticate($user);

        $this->getJson('/api/v1/user/kca/applications/current')
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->postJson('/api/v1/user/kca/applications', [
            'application_data' => ['motivation' => 'Called to serve', 'phone' => '+2348012345678'],
            'finalize' => false,
        ])->assertCreated()
            ->assertJsonPath('data.status', KcaApplicationState::Draft->value)
            ->assertJsonPath('data.application_data.motivation', 'Called to serve')
            ->assertJsonPath('data.application_data.phone', '+2348012345678');

        $this->assertSame('+2348012345678', $user->person?->fresh(['profile'])?->profile?->phone);

        $this->getJson('/api/v1/user/kca/applications/current')
            ->assertOk()
            ->assertJsonPath('data.status', KcaApplicationState::Draft->value)
            ->assertJsonPath('data.application_data.motivation', 'Called to serve');

        $this->postJson('/api/v1/user/kca/applications', [
            'application_data' => ['motivation' => 'Called to serve', 'church' => 'Lagos'],
            'finalize' => true,
        ])->assertOk()
            ->assertJsonPath('data.status', KcaApplicationState::Received->value)
            ->assertJsonPath('data.application_data.church', 'Lagos');
    }

    public function test_current_application_requires_authentication(): void
    {
        $this->getJson('/api/v1/user/kca/applications/current')
            ->assertUnauthorized();
    }

    private function authenticate(User $user, bool $recentMfa = false): SecuritySession
    {
        $securitySession = SecuritySession::factory()->for($user)->create();
        $session = ['security_session_id' => $securitySession->public_id];

        if ($recentMfa) {
            $session['auth.mfa_verified_at'] = now()->utc()->toIso8601String();
        }

        $this->actingAs($user);
        $this->withSession($session);

        return $securitySession;
    }
}
