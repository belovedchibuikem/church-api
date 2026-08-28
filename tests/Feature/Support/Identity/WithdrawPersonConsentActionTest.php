<?php

namespace Tests\Feature\Support\Identity;

use App\Models\AuditEvent;
use App\Models\Person;
use App\Models\User;
use App\Support\Identity\GrantPersonConsentAction;
use App\Support\Identity\WithdrawPersonConsentAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvalidArgumentException;
use Tests\TestCase;

class WithdrawPersonConsentActionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_withdraws_consent_and_preserves_the_grant_evidence(): void
    {
        $this->freezeSecond();
        $actor = User::factory()->create();
        $person = Person::factory()->create();
        $consent = $this->app->make(GrantPersonConsentAction::class)->handle(
            $person,
            'communications.updates',
            '1.0',
            'self_service',
            $actor,
        );
        $grantedAt = $consent->granted_at;

        $withdrawnConsent = $this->app->make(WithdrawPersonConsentAction::class)
            ->handle($consent, 'admin_request', $actor);

        $this->assertTrue($withdrawnConsent->isWithdrawn());
        $this->assertSame('self_service', $withdrawnConsent->source);
        $this->assertSame('admin_request', $withdrawnConsent->withdrawal_source);
        $this->assertTrue($withdrawnConsent->granted_at->equalTo($grantedAt));
        $this->assertTrue($withdrawnConsent->withdrawn_at->equalTo(now()));

        $withdrawal = AuditEvent::query()->latest('id')->firstOrFail();
        $this->assertSame('privacy.consent.withdrawn', $withdrawal->action);
        $this->assertSame('communications.updates', $withdrawal->metadata['purpose']);
        $this->assertSame('1.0', $withdrawal->metadata['policy_version']);
        $this->assertSame('admin_request', $withdrawal->metadata['source']);
    }

    public function test_repeating_a_consent_withdrawal_is_idempotent(): void
    {
        $person = Person::factory()->create();
        $consent = $this->app->make(GrantPersonConsentAction::class)->handle(
            $person,
            'communications.updates',
            '1.0',
            'self_service',
        );
        $action = $this->app->make(WithdrawPersonConsentAction::class);

        $action->handle($consent, 'self_service');
        $withdrawnConsent = $action->handle($consent, 'self_service');

        $this->assertTrue($withdrawnConsent->isWithdrawn());
        $this->assertSame(2, AuditEvent::query()->count());
    }

    public function test_rejects_an_unstable_withdrawal_source_without_changing_consent(): void
    {
        $person = Person::factory()->create();
        $consent = $this->app->make(GrantPersonConsentAction::class)->handle(
            $person,
            'communications.updates',
            '1.0',
            'self_service',
        );
        $wasRejected = false;

        try {
            $this->app->make(WithdrawPersonConsentAction::class)
                ->handle($consent, 'Requested by administrator');
            $this->fail('Expected the unstable withdrawal source to be rejected.');
        } catch (InvalidArgumentException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertFalse($consent->fresh()->isWithdrawn());
        $this->assertSame(1, AuditEvent::query()->count());
    }

    public function test_withdrawal_evidence_cannot_be_mass_assigned(): void
    {
        $person = Person::factory()->create();
        $consent = $this->app->make(GrantPersonConsentAction::class)->handle(
            $person,
            'communications.updates',
            '1.0',
            'self_service',
        );

        $consent->fill([
            'withdrawal_source' => 'attacker_supplied',
            'withdrawn_at' => now(),
        ]);

        $this->assertFalse($consent->isDirty('withdrawal_source'));
        $this->assertFalse($consent->isDirty('withdrawn_at'));
        $this->assertFalse($consent->isWithdrawn());
    }
}
