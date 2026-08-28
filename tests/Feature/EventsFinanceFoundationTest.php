<?php

namespace Tests\Feature;

use App\Events\Actions\RecordEventAttendanceAction;
use App\Events\Actions\RecordEventFeedbackAction;
use App\Events\Actions\RegisterForEventAction;
use App\Events\EventRegistrationStatus;
use App\Exceptions\PaymentGovernanceDeniedException;
use App\Exceptions\PaymentVerificationException;
use App\Finance\Actions\CreatePaymentIntentAction;
use App\Finance\Actions\ReconcilePaymentWebhookAction;
use App\Finance\Actions\RecordPaymentDisputeAction;
use App\Finance\Actions\RequestPaymentRefundAction;
use App\Finance\Contracts\PaymentGovernancePolicy;
use App\Finance\Contracts\WebhookVerifier;
use App\Finance\Data\PaymentWebhookEnvelope;
use App\Finance\Data\VerifiedPaymentWebhook;
use App\Finance\PaymentDisputeStatus;
use App\Models\AuditEvent;
use App\Models\EventRegistration;
use App\Models\MinistryEvent;
use App\Models\PaymentDispute;
use App\Models\PaymentIntent;
use App\Models\PaymentReconciliation;
use App\Models\PaymentTransaction;
use App\Models\Person;
use App\Models\User;
use App\Support\Audit\RecordAuditEventAction;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class EventsFinanceFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('payment_provider_configurations')->update(['is_active' => false]);
        config()->set('finance.governance_mode', 'deny');
    }

    public function test_free_event_registration_attendance_and_feedback_follow_the_lifecycle(): void
    {
        $event = MinistryEvent::factory()->create();
        $person = Person::factory()->create();
        $registration = $this->app->make(RegisterForEventAction::class)->handle($event, $person, 'event-register-001');
        $sameRegistration = $this->app->make(RegisterForEventAction::class)->handle($event, $person, 'event-register-001');
        $this->app->make(RecordEventAttendanceAction::class)->handle($registration, 'manual');
        $this->app->make(RecordEventFeedbackAction::class)->handle($registration, 5);

        $this->assertSame($registration->getKey(), $sameRegistration->getKey());
        $this->assertSame(EventRegistrationStatus::FeedbackRecorded, $registration->fresh()->status);
    }

    public function test_payment_governance_denies_by_default(): void
    {
        $event = MinistryEvent::factory()->create(['fee_amount_minor' => 2500, 'fee_currency' => 'USD']);
        $registration = EventRegistration::factory()->for($event, 'event')->create(['status' => EventRegistrationStatus::PaymentPending]);

        $this->expectException(PaymentGovernanceDeniedException::class);
        $this->app->make(CreatePaymentIntentAction::class)->handle($registration, 'payment-intent-001');
    }

    public function test_payment_intent_uses_the_current_locked_event_fee_for_governance_and_persistence(): void
    {
        $event = MinistryEvent::factory()->create(['fee_amount_minor' => 2500, 'fee_currency' => 'USD']);
        $person = Person::factory()->create();
        $registration = EventRegistration::factory()->for($event, 'event')->for($person)->create(['status' => EventRegistrationStatus::PaymentPending]);
        $registration->load('event');
        MinistryEvent::query()->whereKey($event->getKey())->update(['fee_amount_minor' => 4200, 'fee_currency' => 'EUR']);
        $governance = new class implements PaymentGovernancePolicy
        {
            public ?string $paymentCurrency = null;

            public function allowsPaymentIntent(string $purposeCode, string $currency, ?Person $payer): bool
            {
                $this->paymentCurrency = $currency;

                return true;
            }

            public function allowsRefund(PaymentTransaction $transaction, int $amountMinor, ?User $actor): bool
            {
                return false;
            }
        };
        $action = new CreatePaymentIntentAction($this->app->make(RecordAuditEventAction::class), $governance);

        $intent = $action->handle($registration, 'payment-intent-current-fee');

        $this->assertSame('EUR', $governance->paymentCurrency);
        $this->assertSame(4200, $intent->amount_minor);
        $this->assertSame('EUR', $intent->currency);
        $this->assertSame($person->getKey(), $intent->payer_person_id);
    }

    public function test_refund_rejects_an_invalid_idempotency_key_before_governance(): void
    {
        $transaction = PaymentTransaction::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Refund idempotency keys must contain 8 to 191 characters.');

        $this->app->make(RequestPaymentRefundAction::class)->handle($transaction, 100, 'requested_by_payer', 'short');
    }

    public function test_refund_governance_receives_the_current_locked_transaction(): void
    {
        $transaction = PaymentTransaction::factory()->create(['amount_minor' => 2000, 'currency' => 'USD']);
        PaymentReconciliation::factory()->for($transaction, 'transaction')->create();
        PaymentTransaction::query()->whereKey($transaction->getKey())->update(['currency' => 'EUR']);
        $governance = new class implements PaymentGovernancePolicy
        {
            public ?string $refundCurrency = null;

            public function allowsPaymentIntent(string $purposeCode, string $currency, ?Person $payer): bool
            {
                return false;
            }

            public function allowsRefund(PaymentTransaction $transaction, int $amountMinor, ?User $actor): bool
            {
                $this->refundCurrency = $transaction->currency;

                return true;
            }
        };
        $action = new RequestPaymentRefundAction($this->app->make(RecordAuditEventAction::class), $governance);

        $refund = $action->handle($transaction, 500, 'requested_by_payer', 'refund-request-current-transaction');

        $this->assertSame('EUR', $governance->refundCurrency);
        $this->assertSame('EUR', $refund->currency);
        $this->assertSame(500, $refund->amount_minor);
    }

    public function test_unverified_webhook_is_rejected_before_financial_records_are_created(): void
    {
        $this->expectException(PaymentVerificationException::class);
        $this->app->make(ReconcilePaymentWebhookAction::class)->handle(
            new PaymentWebhookEnvelope('unknown', 'event-1', null, [], new DateTimeImmutable),
        );
    }

    public function test_repeated_verified_payment_webhook_returns_the_existing_reconciliation(): void
    {
        $intent = PaymentIntent::factory()->create(['amount_minor' => 2500, 'currency' => 'USD']);
        $verified = new VerifiedPaymentWebhook(
            type: 'payment_succeeded',
            providerCode: 'test_gateway',
            eventId: 'payment-event-001',
            paymentIntentPublicId: $intent->public_id,
            providerReference: 'provider-payment-001',
            amountMinor: 2500,
            currency: 'USD',
            occurredAt: new DateTimeImmutable('2026-08-26T10:00:00+00:00'),
        );
        $verifier = new class($verified) implements WebhookVerifier
        {
            public function __construct(private readonly VerifiedPaymentWebhook $verified) {}

            public function verify(PaymentWebhookEnvelope $envelope): ?VerifiedPaymentWebhook
            {
                return $this->verified;
            }
        };
        $action = new ReconcilePaymentWebhookAction($this->app->make(RecordAuditEventAction::class), $verifier);
        $envelope = new PaymentWebhookEnvelope('test_gateway', 'payment-event-001', 'verified', [], new DateTimeImmutable);

        $first = $action->handle($envelope);
        $retry = $action->handle($envelope);

        $this->assertSame($first->getKey(), $retry->getKey());
        $this->assertSame(1, PaymentTransaction::query()->where('provider_event_hash', hash_hmac('sha256', 'test_gateway|payment-event-001', (string) config('app.key')))->count());
        $this->assertSame(1, AuditEvent::query()->where('action', 'finance.payment.reconciled')->count());
    }

    public function test_repeated_verified_dispute_webhook_returns_the_existing_dispute(): void
    {
        $providerReference = 'provider-payment-002';
        $transaction = PaymentTransaction::factory()->create([
            'provider_reference_hash' => hash_hmac('sha256', "test_gateway|{$providerReference}", (string) config('app.key')),
        ]);
        $verified = new VerifiedPaymentWebhook(
            type: 'dispute_opened',
            providerCode: 'test_gateway',
            eventId: 'dispute-event-001',
            paymentIntentPublicId: 'not-used-for-disputes',
            providerReference: $providerReference,
            amountMinor: 1000,
            currency: 'USD',
            occurredAt: new DateTimeImmutable('2026-08-26T11:00:00+00:00'),
            reasonCode: 'provider_reported',
            disputeCaseId: 'dispute-case-001',
            disputeStatus: PaymentDisputeStatus::Opened,
        );
        $verifier = new class($verified) implements WebhookVerifier
        {
            public function __construct(private readonly VerifiedPaymentWebhook $verified) {}

            public function verify(PaymentWebhookEnvelope $envelope): ?VerifiedPaymentWebhook
            {
                return $this->verified;
            }
        };
        $action = new RecordPaymentDisputeAction($this->app->make(RecordAuditEventAction::class), $verifier);
        $envelope = new PaymentWebhookEnvelope('test_gateway', 'dispute-event-001', 'verified', [], new DateTimeImmutable);

        $first = $action->handle($envelope);
        $retry = $action->handle($envelope);

        $this->assertSame($first->getKey(), $retry->getKey());
        $this->assertSame($transaction->getKey(), $retry->payment_transaction_id);
        $this->assertSame(1, PaymentDispute::query()->where('provider_event_hash', hash_hmac('sha256', 'test_gateway|dispute-event-001', (string) config('app.key')))->count());
        $this->assertSame(1, AuditEvent::query()->where('action', 'finance.dispute.recorded')->count());
    }
}
