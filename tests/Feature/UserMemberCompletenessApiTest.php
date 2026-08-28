<?php

namespace Tests\Feature;

use App\Church\ChurchMembershipStatus;
use App\Models\AuditEvent;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\EventRegistration;
use App\Models\FileAsset;
use App\Models\HomeChurch;
use App\Models\Person;
use App\Models\SecuritySession;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UserMemberCompletenessApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_returns_own_event_registration_ticket_without_mfa(): void
    {
        $user = User::factory()->withPerson()->create();
        $this->authenticate($user);
        $owned = EventRegistration::factory()->create(['person_id' => $user->person->getKey()]);
        $other = EventRegistration::factory()->create();

        $this->getJson("/api/v1/user/events/registrations/{$owned->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $owned->public_id)
            ->assertJsonPath('data.person_id', $user->person->public_id)
            ->assertJsonPath('data.status', $owned->status->value);

        $this->getJson("/api/v1/user/events/registrations/{$other->public_id}")
            ->assertNotFound();
    }

    public function test_event_ticket_requires_authentication(): void
    {
        $registration = EventRegistration::factory()->create();

        $this->getJson("/api/v1/user/events/registrations/{$registration->public_id}")
            ->assertUnauthorized();
    }

    public function test_starts_membership_for_authenticated_person_and_lists_it(): void
    {
        $user = User::factory()->withPerson()->create();
        $this->authenticate($user, recentMfa: true);
        $church = Church::factory()->create();
        $homeChurch = HomeChurch::factory()->for($church)->create();

        $this->postJson("/api/v1/user/churches/{$church->public_id}/memberships", [
            'home_church_id' => $homeChurch->public_id,
        ])->assertCreated()
            ->assertJsonPath('data.person_id', $user->person->public_id)
            ->assertJsonPath('data.church_id', $church->public_id)
            ->assertJsonPath('data.home_church_id', $homeChurch->public_id)
            ->assertJsonPath('data.status', 'active');

        $this->getJson('/api/v1/user/memberships')
            ->assertOk()
            ->assertJsonPath('data.0.person_id', $user->person->public_id)
            ->assertJsonPath('data.0.church_id', $church->public_id)
            ->assertJsonPath('meta.pagination.total', 1);
    }

    public function test_membership_start_requires_a_linked_person(): void
    {
        $unlinked = User::factory()->create();
        $this->authenticate($unlinked, recentMfa: true);
        $church = Church::factory()->create();

        $this->postJson("/api/v1/user/churches/{$church->public_id}/memberships")
            ->assertStatus(422);
    }

    public function test_duplicate_active_membership_is_rejected(): void
    {
        $user = User::factory()->withPerson()->create();
        $this->authenticate($user, recentMfa: true);
        $church = Church::factory()->create();
        ChurchMembership::factory()->create([
            'person_id' => $user->person->getKey(),
            'church_id' => $church->getKey(),
        ]);

        $this->postJson("/api/v1/user/churches/{$church->public_id}/memberships")
            ->assertStatus(422);
    }

    public function test_membership_and_report_mutations_require_recent_mfa(): void
    {
        $member = User::factory()->withPerson()->create();
        $this->authenticate($member);
        $church = Church::factory()->create();

        $this->postJson("/api/v1/user/churches/{$church->public_id}/memberships")
            ->assertForbidden();
        $this->postJson('/api/v1/user/home-churches/01ARZ3NDEKTSV4RRFFQ69G5FAV/reports', [
            'summary' => 'Weekly attendance note.',
        ])->assertForbidden();
    }

    public function test_home_church_dashboard_is_limited_to_active_members_or_leaders(): void
    {
        $member = User::factory()->withPerson()->create();
        $this->authenticate($member);
        $church = Church::factory()->create();
        $homeChurch = HomeChurch::factory()->for($church)->create();
        ChurchMembership::factory()->create([
            'person_id' => $member->person->getKey(),
            'church_id' => $church->getKey(),
            'home_church_id' => $homeChurch->getKey(),
        ]);

        $this->getJson("/api/v1/user/home-churches/{$homeChurch->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $homeChurch->public_id)
            ->assertJsonPath('data.name', $homeChurch->name)
            ->assertJsonPath('data.status', $homeChurch->status->value)
            ->assertJsonPath('data.church_name', $church->name)
            ->assertJsonPath('data.membership_status', 'active');

        $leader = User::factory()->withPerson()->create();
        $this->authenticate($leader);
        $led = HomeChurch::factory()->create(['leader_person_id' => $leader->person->getKey()]);

        $this->getJson("/api/v1/user/home-churches/{$led->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $led->public_id)
            ->assertJsonPath('data.membership_status', null);

        $stranger = User::factory()->withPerson()->create();
        $this->authenticate($stranger);

        $this->getJson("/api/v1/user/home-churches/{$homeChurch->public_id}")
            ->assertNotFound();
    }

    public function test_ended_membership_cannot_read_home_church_dashboard(): void
    {
        $member = User::factory()->withPerson()->create();
        $this->authenticate($member);
        $church = Church::factory()->create();
        $homeChurch = HomeChurch::factory()->for($church)->create();
        $membership = ChurchMembership::factory()->create([
            'person_id' => $member->person->getKey(),
            'church_id' => $church->getKey(),
            'home_church_id' => $homeChurch->getKey(),
        ]);
        $membership->forceFill([
            'status' => ChurchMembershipStatus::Ended,
            'active_marker' => null,
            'ended_at' => now(),
            'end_reason_code' => 'membership_ended',
        ])->save();

        $this->getJson("/api/v1/user/home-churches/{$homeChurch->public_id}")
            ->assertNotFound();
    }

    public function test_submits_audit_backed_home_church_report(): void
    {
        $user = User::factory()->withPerson()->create();
        $this->authenticate($user, recentMfa: true);
        $church = Church::factory()->create();
        $homeChurch = HomeChurch::factory()->for($church)->create();
        $membership = ChurchMembership::factory()->create([
            'person_id' => $user->person->getKey(),
            'church_id' => $church->getKey(),
            'home_church_id' => $homeChurch->getKey(),
        ]);

        $this->postJson("/api/v1/user/home-churches/{$homeChurch->public_id}/reports", [
            'summary' => 'Weekly attendance was strong and visitors were welcomed.',
            'period_code' => '2026-W34',
        ])->assertCreated()
            ->assertJsonPath('data.id', $membership->public_id)
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.submitted_at', fn (mixed $value): bool => is_string($value));

        $audit = AuditEvent::query()->where('action', 'home_church.report.submitted')->sole();
        $this->assertSame($homeChurch->public_id, $audit->target_id);
        $this->assertSame('home_church', $audit->target_type);
        $this->assertSame('Weekly attendance was strong and visitors were welcomed.', $audit->metadata['summary']);
    }

    public function test_lists_file_assets_owned_by_the_current_person(): void
    {
        $user = User::factory()->withPerson()->create();
        $this->authenticate($user);
        $owned = FileAsset::factory()->create(['owner_person_id' => $user->person->getKey()]);
        FileAsset::factory()->create(['owner_person_id' => Person::factory()]);

        $this->getJson('/api/v1/user/files')
            ->assertOk()
            ->assertJsonPath('data.0.id', $owned->public_id)
            ->assertJsonPath('data.0.owner_person_id', $user->person->public_id)
            ->assertJsonPath('meta.pagination.total', 1);
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
