<?php

namespace Tests\Feature\Support\Communication;

use App\Communication\CommunicationBroadcastStatus;
use App\Communication\CommunicationChannel;
use App\Communication\CommunicationDeliveryStatus;
use App\Communication\CommunicationRecipientStatus;
use App\Models\CommunicationBroadcast;
use App\Models\CommunicationDeliveryAttempt;
use App\Models\CommunicationNotification;
use App\Models\CommunicationRecipient;
use App\Models\CommunicationTemplate;
use App\Models\User;
use App\Support\Communication\AttemptCommunicationDeliveryAction;
use App\Support\Communication\CreateInAppNotificationAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CommunicationDeliveryFoundationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_disabled_gateway_suppresses_delivery_and_retries_idempotently(): void
    {
        $actor = User::factory()->create();
        $template = CommunicationTemplate::factory()->create(['channel' => CommunicationChannel::Email]);
        $broadcast = CommunicationBroadcast::factory()->create([
            'communication_template_id' => $template->getKey(),
            'channel' => CommunicationChannel::Email,
            'status' => CommunicationBroadcastStatus::Prepared,
            'prepared_at' => now(),
        ]);
        $recipient = CommunicationRecipient::factory()->create([
            'communication_broadcast_id' => $broadcast->getKey(),
            'status' => CommunicationRecipientStatus::Eligible,
        ]);
        $action = $this->app->make(AttemptCommunicationDeliveryAction::class);

        $first = $action->handle($recipient, 'delivery-retry-key', $actor);
        $retry = $action->handle($recipient, 'delivery-retry-key', $actor);

        $this->assertTrue($first->is($retry));
        $this->assertSame(CommunicationDeliveryStatus::Suppressed, $retry->status);
        $this->assertSame('provider_unconfigured', $retry->result_code);
        $this->assertArrayNotHasKey('idempotency_key_hash', $retry->toArray());
        $this->assertSame(1, CommunicationDeliveryAttempt::query()->count());
    }

    public function test_in_app_notification_creation_is_local_audited_and_idempotent(): void
    {
        $actor = User::factory()->create();
        $template = CommunicationTemplate::factory()->create(['channel' => CommunicationChannel::InApp]);
        $broadcast = CommunicationBroadcast::factory()->create([
            'communication_template_id' => $template->getKey(),
            'channel' => CommunicationChannel::InApp,
            'status' => CommunicationBroadcastStatus::Prepared,
            'prepared_at' => now(),
        ]);
        $recipient = CommunicationRecipient::factory()->create([
            'communication_broadcast_id' => $broadcast->getKey(),
            'status' => CommunicationRecipientStatus::Eligible,
        ]);
        $action = $this->app->make(CreateInAppNotificationAction::class);

        $first = $action->handle($recipient, $actor);
        $retry = $action->handle($recipient, $actor);

        $this->assertTrue($first->is($retry));
        $this->assertSame(1, CommunicationNotification::query()->count());
    }
}
