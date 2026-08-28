<?php

namespace Tests\Feature;

use App\Exceptions\IdentityLinkConflictException;
use App\Models\AuditEvent;
use App\Models\Person;
use App\Models\User;
use App\Support\Identity\LinkUserToPersonAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdentityFoundationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_links_a_user_to_one_canonical_person_and_records_the_change(): void
    {
        $correlationId = '60fe46ef-f3b7-46ba-a797-c2717545c3c8';
        Context::add('correlation_id', $correlationId);
        $actor = User::factory()->create();
        $user = User::factory()->create();
        $person = Person::factory()->withProfile()->create();

        $linkedUser = $this->app->make(LinkUserToPersonAction::class)
            ->handle($user, $person, $actor)
            ->load('person.profile');

        $this->assertSame($person->getKey(), $linkedUser->person_id);
        $this->assertSame($person->public_id, $linkedUser->person->public_id);
        $this->assertTrue(Str::isUlid($linkedUser->person->public_id));
        $this->assertNotNull($linkedUser->person->profile);

        $auditEvent = AuditEvent::query()->sole();
        $this->assertSame('identity.person.user_linked', $auditEvent->action);
        $this->assertSame($actor->getKey(), $auditEvent->actor_user_id);
        $this->assertSame('person', $auditEvent->target_type);
        $this->assertSame($person->public_id, $auditEvent->target_id);
        $this->assertSame($correlationId, $auditEvent->correlation_id);
    }

    public function test_repeating_the_same_identity_link_is_idempotent(): void
    {
        $user = User::factory()->create();
        $person = Person::factory()->create();
        $action = $this->app->make(LinkUserToPersonAction::class);

        $action->handle($user, $person);
        $linkedUser = $action->handle($user, $person);

        $this->assertSame($person->getKey(), $linkedUser->person_id);
        $this->assertSame(1, AuditEvent::query()->count());
    }

    public function test_rejects_linking_a_user_to_a_second_person(): void
    {
        $firstPerson = Person::factory()->create();
        $secondPerson = Person::factory()->create();
        $user = User::factory()->create();
        $action = $this->app->make(LinkUserToPersonAction::class);
        $action->handle($user, $firstPerson);
        $wasRejected = false;

        try {
            $action->handle($user, $secondPerson);
            $this->fail('Expected the conflicting identity link to be rejected.');
        } catch (IdentityLinkConflictException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame($firstPerson->getKey(), $user->fresh()->person_id);
        $this->assertSame(1, AuditEvent::query()->count());
    }

    public function test_rejects_linking_a_person_to_a_second_user(): void
    {
        $person = Person::factory()->create();
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $action = $this->app->make(LinkUserToPersonAction::class);
        $action->handle($firstUser, $person);
        $wasRejected = false;

        try {
            $action->handle($secondUser, $person);
            $this->fail('Expected the conflicting identity link to be rejected.');
        } catch (IdentityLinkConflictException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertNull($secondUser->fresh()->person_id);
        $this->assertSame($firstUser->getKey(), $person->user()->sole()->getKey());
        $this->assertSame(1, AuditEvent::query()->count());
    }

    public function test_person_link_cannot_be_mass_assigned_to_a_user(): void
    {
        $person = Person::factory()->create();
        $user = User::factory()->create();

        $user->fill(['person_id' => $person->getKey()]);

        $this->assertNull($user->person_id);
        $this->assertFalse($user->isDirty('person_id'));
    }
}
