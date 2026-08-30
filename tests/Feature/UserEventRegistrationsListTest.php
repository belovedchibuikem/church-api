<?php

namespace Tests\Feature;

use App\Models\EventRegistration;
use App\Models\SecuritySession;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UserEventRegistrationsListTest extends TestCase
{
    use DatabaseTransactions;

    public function test_lists_own_event_registrations(): void
    {
        $user = User::factory()->withPerson()->create();
        $this->authenticate($user);
        $owned = EventRegistration::factory()->create([
            'person_id' => $user->person->getKey(),
            'ticket_code' => 'EVT-TESTCODE01',
        ]);
        EventRegistration::factory()->create();

        $this->getJson('/api/v1/user/events/registrations')
            ->assertOk()
            ->assertJsonPath('data.0.id', $owned->public_id)
            ->assertJsonPath('data.0.ticket_code', 'EVT-TESTCODE01')
            ->assertJsonPath('data.0.qr_payload', 'fhc:ticket:EVT-TESTCODE01')
            ->assertJsonPath('meta.pagination.total', 1);
    }

    public function test_event_registrations_list_requires_authentication(): void
    {
        $this->getJson('/api/v1/user/events/registrations')
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
