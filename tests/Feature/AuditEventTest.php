<?php

namespace Tests\Feature;

use App\Exceptions\AuditEventImmutableException;
use App\Models\AuditEvent;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class AuditEventTest extends TestCase
{
    use DatabaseTransactions;

    public function test_records_an_append_only_audit_event_with_request_context(): void
    {
        $this->freezeSecond();
        $correlationId = '223043a6-26af-4c31-9854-c61fab33b922';
        Context::add('correlation_id', $correlationId);
        $actor = User::factory()->create();

        $event = $this->app->make(RecordAuditEventAction::class)->handle(
            new AuditEventData(
                action: 'security.session.revoked',
                actor: $actor,
                targetType: 'session',
                targetId: 'session-123',
                scopeType: 'global',
                scopeId: 'platform',
                metadata: ['reason' => 'user_requested'],
            ),
        );

        $this->assertModelExists($event);
        $this->assertTrue(Str::isUlid($event->public_id));
        $this->assertSame($correlationId, $event->correlation_id);
        $this->assertSame($actor->getKey(), $event->actor_user_id);
        $this->assertSame('security.session.revoked', $event->action);
        $this->assertSame('session', $event->target_type);
        $this->assertSame('session-123', $event->target_id);
        $this->assertSame('global', $event->scope_type);
        $this->assertSame('platform', $event->scope_id);
        $this->assertSame(['reason' => 'user_requested'], $event->metadata);
        $this->assertTrue($event->occurred_at->equalTo(now()));
    }

    public function test_rejects_an_invalid_audit_action_without_writing_a_record(): void
    {
        $wasRejected = false;

        try {
            $this->app->make(RecordAuditEventAction::class)->handle(
                new AuditEventData(action: 'Invalid Action'),
            );
            $this->fail('Expected the invalid audit action to be rejected.');
        } catch (InvalidArgumentException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_rejects_an_incomplete_target_without_writing_a_record(): void
    {
        $wasRejected = false;

        try {
            $this->app->make(RecordAuditEventAction::class)->handle(
                new AuditEventData(
                    action: 'identity.person.updated',
                    targetType: 'person',
                ),
            );
            $this->fail('Expected the incomplete audit target to be rejected.');
        } catch (InvalidArgumentException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_rejects_updating_an_existing_audit_event(): void
    {
        $event = AuditEvent::factory()->create(['action' => 'identity.person.created']);
        $wasRejected = false;

        try {
            $event->update(['action' => 'identity.person.changed']);
            $this->fail('Expected the audit update to be rejected.');
        } catch (AuditEventImmutableException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame('identity.person.created', $event->fresh()->action);
    }

    public function test_rejects_deleting_an_existing_audit_event(): void
    {
        $event = AuditEvent::factory()->create();
        $wasRejected = false;

        try {
            $event->delete();
            $this->fail('Expected the audit deletion to be rejected.');
        } catch (AuditEventImmutableException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertModelExists($event);
    }
}
