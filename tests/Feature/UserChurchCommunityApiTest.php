<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchGroup;
use App\Models\ChurchMembership;
use App\Models\SecuritySession;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UserChurchCommunityApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_lists_groups_empty_when_member_has_no_published_groups(): void
    {
        $user = User::factory()->withPerson()->create();
        $this->authenticate($user);
        $church = Church::factory()->create();
        ChurchMembership::factory()->create([
            'person_id' => $user->person->getKey(),
            'church_id' => $church->getKey(),
        ]);

        $this->getJson('/api/v1/user/groups')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.pagination.total', 0);
    }

    public function test_join_group_requires_active_church_membership(): void
    {
        $user = User::factory()->withPerson()->create();
        $this->authenticate($user);
        $church = Church::factory()->create();
        $group = new ChurchGroup;
        $group->forceFill([
            'church_id' => $church->getKey(),
            'name' => 'Worship Team',
            'description' => 'Sunday musicians',
            'is_published' => true,
        ])->save();

        $this->postJson("/api/v1/user/groups/{$group->public_id}/join")
            ->assertNotFound();
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
