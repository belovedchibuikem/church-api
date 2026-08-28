<?php

namespace Tests\Feature\Support\Communication;

use App\Communication\CommunicationChannel;
use App\Communication\CommunicationKind;
use App\Exceptions\CommunicationIdempotencyConflictException;
use App\Models\AuditEvent;
use App\Models\CommunicationAudience;
use App\Models\CommunicationBroadcast;
use App\Models\CommunicationTemplate;
use App\Models\User;
use App\Support\Communication\CommunicationPurpose;
use App\Support\Communication\PrepareCommunicationBroadcastAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PrepareCommunicationBroadcastActionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_creates_an_audited_draft_and_retries_idempotently_without_exposing_the_key_hash(): void
    {
        $actor = User::factory()->create();
        $template = CommunicationTemplate::factory()->create(['channel' => CommunicationChannel::Email]);
        $audience = CommunicationAudience::factory()->create();
        $action = $this->app->make(PrepareCommunicationBroadcastAction::class);

        $first = $action->handle(
            $template,
            $audience,
            CommunicationKind::Newsletter,
            CommunicationChannel::Email,
            new CommunicationPurpose('communications.newsletters'),
            'stable-client-retry-key',
            $actor,
        );
        $retry = $action->handle(
            $template,
            $audience,
            CommunicationKind::Newsletter,
            CommunicationChannel::Email,
            new CommunicationPurpose('communications.newsletters'),
            'stable-client-retry-key',
            $actor,
        );

        $this->assertTrue($first->is($retry));
        $this->assertArrayNotHasKey('idempotency_key_hash', $first->toArray());
        $this->assertSame(1, CommunicationBroadcast::query()->count());
        $this->assertSame(1, AuditEvent::query()->count());
    }

    public function test_it_rejects_reusing_an_idempotency_key_for_different_input(): void
    {
        $actor = User::factory()->create();
        $template = CommunicationTemplate::factory()->create(['channel' => CommunicationChannel::Email]);
        $action = $this->app->make(PrepareCommunicationBroadcastAction::class);

        $action->handle(
            $template,
            CommunicationAudience::factory()->create(),
            CommunicationKind::Broadcast,
            CommunicationChannel::Email,
            new CommunicationPurpose('communications.updates'),
            'conflicting-key',
            $actor,
        );

        $this->expectException(CommunicationIdempotencyConflictException::class);

        $action->handle(
            $template,
            CommunicationAudience::factory()->create(),
            CommunicationKind::Broadcast,
            CommunicationChannel::Email,
            new CommunicationPurpose('communications.updates'),
            'conflicting-key',
            $actor,
        );
    }
}
