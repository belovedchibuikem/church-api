<?php

namespace Tests\Feature\Support\Identity;

use App\Exceptions\ConsentConflictException;
use App\Models\AuditEvent;
use App\Models\Person;
use App\Models\PersonConsent;
use App\Models\User;
use App\Support\Identity\GrantPersonConsentAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GrantPersonConsentActionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_records_a_versioned_consent_grant_for_the_canonical_person(): void
    {
        $this->freezeSecond();
        $actor = User::factory()->create();
        $person = Person::factory()->withProfile()->create();

        $consent = $this->app->make(GrantPersonConsentAction::class)->handle(
            $person,
            'communications.updates',
            '2026.08',
            'self_service',
            $actor,
        );

        $this->assertModelExists($consent);
        $this->assertTrue(Str::isUlid($consent->public_id));
        $this->assertSame($person->getKey(), $consent->person_id);
        $this->assertSame('communications.updates', $consent->purpose);
        $this->assertSame('2026.08', $consent->policy_version);
        $this->assertSame('self_service', $consent->source);
        $this->assertTrue($consent->granted_at->equalTo(now()));
        $this->assertFalse($consent->isWithdrawn());

        $auditEvent = AuditEvent::query()->sole();
        $this->assertSame('privacy.consent.granted', $auditEvent->action);
        $this->assertSame($consent->public_id, $auditEvent->target_id);
        $this->assertSame('communications.updates', $auditEvent->metadata['purpose']);
        $this->assertSame('2026.08', $auditEvent->metadata['policy_version']);
        $this->assertSame('self_service', $auditEvent->metadata['source']);
    }

    public function test_repeating_the_same_active_consent_is_idempotent(): void
    {
        $person = Person::factory()->create();
        $action = $this->app->make(GrantPersonConsentAction::class);

        $firstConsent = $action->handle(
            $person,
            'communications.updates',
            '1.0',
            'self_service',
        );
        $secondConsent = $action->handle(
            $person,
            'communications.updates',
            '1.0',
            'self_service',
        );

        $this->assertSame($firstConsent->getKey(), $secondConsent->getKey());
        $this->assertSame(1, PersonConsent::query()->count());
        $this->assertSame(1, AuditEvent::query()->count());
    }

    public function test_rejects_a_second_active_version_for_the_same_purpose(): void
    {
        $person = Person::factory()->create();
        $action = $this->app->make(GrantPersonConsentAction::class);
        $action->handle($person, 'communications.updates', '1.0', 'self_service');
        $wasRejected = false;

        try {
            $action->handle($person, 'communications.updates', '2.0', 'self_service');
            $this->fail('Expected the conflicting consent grant to be rejected.');
        } catch (ConsentConflictException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame(1, PersonConsent::query()->count());
        $this->assertSame(1, AuditEvent::query()->count());
    }

    #[DataProvider('invalidConsentEvidence')]
    public function test_rejects_invalid_consent_evidence_without_writing_records(
        string $purpose,
        string $policyVersion,
        string $source,
    ): void {
        $person = Person::factory()->create();
        $wasRejected = false;

        try {
            $this->app->make(GrantPersonConsentAction::class)->handle(
                $person,
                $purpose,
                $policyVersion,
                $source,
            );
            $this->fail('Expected the invalid consent evidence to be rejected.');
        } catch (InvalidArgumentException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame(0, PersonConsent::query()->count());
        $this->assertSame(0, AuditEvent::query()->count());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function invalidConsentEvidence(): array
    {
        return [
            'free-form purpose' => ['Marketing consent', '1.0', 'self_service'],
            'unsafe policy version' => ['communications.updates', 'version/1', 'self_service'],
            'free-form source' => ['communications.updates', '1.0', 'Imported by administrator'],
        ];
    }
}
