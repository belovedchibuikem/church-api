<?php

namespace Tests\Feature;

use App\Models\SecuritySession;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserMemberDomainsApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_lists_notifications_empty_for_authenticated_member(): void
    {
        $user = User::factory()->withPerson()->create();
        $this->authenticate($user);

        $this->getJson('/api/v1/user/notifications')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.api_version', 'v1');
    }

    public function test_creates_prayer_request_for_authenticated_person(): void
    {
        $user = User::factory()->withPerson()->create();
        $this->authenticate($user);

        $response = $this->postJson('/api/v1/user/prayers', [
            'subject' => 'Healing for Mum',
            'body' => 'Please pray for recovery this week.',
        ])->assertCreated()
            ->assertJsonPath('data.subject', 'Healing for Mum')
            ->assertJsonPath('data.status', 'open');

        $this->assertNotEmpty($response->json('data.id'));

        $this->getJson('/api/v1/user/prayers')
            ->assertOk()
            ->assertJsonPath('data.0.subject', 'Healing for Mum');
    }

    public function test_giving_intent_is_denied_by_default_governance(): void
    {
        config()->set('finance.governance_mode', 'deny');
        DB::table('payment_provider_configurations')->update(['is_active' => false]);

        $user = User::factory()->withPerson()->create();
        $this->authenticate($user, recentMfa: true);

        $this->postJson('/api/v1/user/payments/giving-intents', [
            'amount_minor' => 5000,
            'currency' => 'NGN',
            'purpose_code' => 'tithe',
        ], [
            'Idempotency-Key' => 'giving-test-key-001',
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'PAYMENT_GOVERNANCE_DENIED')
            ->assertJsonPath(
                'error.message',
                'Payment governance has not enabled giving payment intents.',
            );
    }

    public function test_dashboard_returns_member_aggregate(): void
    {
        $user = User::factory()->withPerson()->create();
        $this->authenticate($user);

        $this->getJson('/api/v1/user/dashboard')
            ->assertOk()
            ->assertJsonPath('data.unread_notification_count', 0)
            ->assertJsonPath('data.open_prayer_count', 0)
            ->assertJsonPath('data.upcoming_note', null)
            ->assertJsonStructure([
                'data' => [
                    'profile',
                    'unread_notification_count',
                    'recent_payment_intents',
                    'open_prayer_count',
                    'upcoming_note',
                ],
            ]);
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
