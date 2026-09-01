<?php

namespace Tests\Feature;

use App\Kca\KcaAssignmentState;
use App\Models\KcaEnrollment;
use App\Models\Person;
use App\Models\SecuritySession;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UserKcaDirectoryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_directory_filters_by_own_and_other_country(): void
    {
        $viewer = User::factory()->withPerson()->create();
        $viewer->person?->profile?->forceFill([
            'country' => 'NG',
            'region' => 'Lagos',
            'locality' => 'Eti-Osa',
        ])->save();

        $sameCountry = User::factory()->withPerson()->create();
        $sameCountry->person?->profile?->forceFill([
            'country' => 'NG',
            'region' => 'Enugu',
            'locality' => 'Enugu North',
        ])->save();

        $otherCountry = User::factory()->withPerson()->create();
        $otherCountry->person?->profile?->forceFill([
            'country' => 'GH',
            'region' => 'Greater Accra',
            'locality' => 'Accra',
        ])->save();

        foreach ([$viewer->person, $sameCountry->person, $otherCountry->person] as $person) {
            $this->assertInstanceOf(Person::class, $person);
            KcaEnrollment::factory()->for($person)->create();
        }

        $this->authenticate($viewer);

        $own = $this->getJson('/api/v1/user/kca/directory?scope=own_country')->assertOk();
        $ownIds = collect($own->json('data'))->pluck('id')->all();
        $this->assertContains($sameCountry->person?->public_id, $ownIds);
        $this->assertNotContains($otherCountry->person?->public_id, $ownIds);

        $other = $this->getJson('/api/v1/user/kca/directory?scope=other_country')->assertOk();
        $otherIds = collect($other->json('data'))->pluck('id')->all();
        $this->assertContains($otherCountry->person?->public_id, $otherIds);
        $this->assertNotContains($sameCountry->person?->public_id, $otherIds);

        $ghana = $this->getJson('/api/v1/user/kca/directory?country=GH')->assertOk();
        $this->assertSame($otherCountry->person?->public_id, $ghana->json('data.0.id'));
        $this->assertSame('Greater Accra', $ghana->json('data.0.region'));
    }

    public function test_practical_service_lists_assigned_departments(): void
    {
        $user = User::factory()->withPerson()->create();
        $this->authenticate($user);
        $enrollment = KcaEnrollment::factory()->for($user->person)->create();
        \App\Models\KcaAssignment::factory()
            ->for($enrollment, 'enrollment')
            ->inState(KcaAssignmentState::Assigned)
            ->create(['title' => 'Evangelism Team']);

        $this->getJson('/api/v1/user/kca/practical-service')
            ->assertOk()
            ->assertJsonPath('data.enrolled', true)
            ->assertJsonPath('data.departments_count', 1)
            ->assertJsonPath('data.departments.0.title', 'Evangelism Team')
            ->assertJsonPath('data.on_track', false);
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
