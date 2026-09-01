<?php

namespace Tests\Feature;

use App\Finance\GivingPurpose;
use App\Models\FileAsset;
use App\Models\PaymentIntent;
use App\Models\SecuritySession;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserGivingPurposeApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_tithe_and_offering_are_stored_as_separate_purposes(): void
    {
        $this->enableLocalGiving();
        $user = User::factory()->withPerson()->create();
        $this->authenticate($user);

        $tithe = $this->postJson('/api/v1/user/payments/giving-intents', [
            'amount_minor' => 10000,
            'currency' => 'NGN',
            'purpose_code' => GivingPurpose::TITHE,
            'idempotency_key' => 'giving-tithe-key-01',
        ])->assertCreated();
        $offering = $this->postJson('/api/v1/user/payments/giving-intents', [
            'amount_minor' => 5000,
            'currency' => 'NGN',
            'purpose_code' => GivingPurpose::OFFERING,
            'idempotency_key' => 'giving-offering-key-01',
        ])->assertCreated();

        $this->assertSame('tithe', $tithe->json('data.purpose_code'));
        $this->assertSame('offering', $offering->json('data.purpose_code'));
        $this->assertNotSame($tithe->json('data.purpose_code'), $offering->json('data.purpose_code'));
    }

    public function test_manual_complete_requires_payment_receipt_upload(): void
    {
        $this->enableLocalGiving();
        $user = User::factory()->withPerson()->create();
        $this->authenticate($user);

        $created = $this->postJson('/api/v1/user/payments/giving-intents', [
            'amount_minor' => 250000,
            'currency' => 'NGN',
            'purpose_code' => GivingPurpose::TITHE,
            'idempotency_key' => 'giving-manual-proof-01',
        ])->assertCreated();
        $intentId = $created->json('data.id');

        $this->postJson("/api/v1/user/payments/giving-intents/{$intentId}/complete")
            ->assertStatus(422);

        $proof = FileAsset::factory()->available()->create([
            'owner_person_id' => $user->person->getKey(),
            'purpose' => GivingPurpose::PROOF_FILE_PURPOSE,
        ]);

        $completed = $this->postJson("/api/v1/user/payments/giving-intents/{$intentId}/complete", [
            'proof_file_asset_id' => $proof->public_id,
        ])->assertOk()
            ->assertJsonPath('data.intent.purpose_code', 'tithe')
            ->assertJsonPath('data.intent.status', 'succeeded')
            ->assertJsonPath('data.receipt.purpose_code', 'tithe')
            ->assertJsonPath('data.receipt.purpose_label', 'Tithe')
            ->assertJsonPath('data.receipt.settlement', 'manual')
            ->assertJsonPath('data.receipt.amount_minor', 250000)
            ->assertJsonPath('data.receipt.currency', 'NGN');

        $receiptId = $completed->json('data.receipt.id');
        $this->getJson("/api/v1/user/payments/receipts/{$receiptId}")
            ->assertOk()
            ->assertJsonPath('data.settlement', 'manual')
            ->assertJsonPath('data.amount_minor', 250000);

        $this->assertTrue(
            PaymentIntent::query()->where('public_id', $intentId)->where('proof_file_asset_id', $proof->getKey())->exists(),
        );
    }

    public function test_manual_event_payment_completes_with_receipt_upload(): void
    {
        $this->enableLocalGiving();
        $user = User::factory()->withPerson()->create();
        $this->authenticate($user);

        $event = \App\Models\MinistryEvent::factory()->published()->create([
            'fee_amount_minor' => 500000,
            'fee_currency' => 'NGN',
        ]);
        $registration = \App\Models\EventRegistration::factory()
            ->for($event, 'event')
            ->for($user->person)
            ->create([
                'status' => \App\Events\EventRegistrationStatus::PaymentPending,
            ]);

        $created = $this->postJson(
            "/api/v1/user/events/registrations/{$registration->public_id}/payment-intents",
            ['idempotency_key' => 'event-manual-proof-01', 'checkout_return' => 'mobile'],
        )->assertCreated();
        $intentId = $created->json('data.id');
        $this->assertSame('event_payment', $created->json('data.purpose_code'));

        $proof = FileAsset::factory()->available()->create([
            'owner_person_id' => $user->person->getKey(),
            'purpose' => GivingPurpose::PROOF_FILE_PURPOSE,
        ]);

        $this->postJson("/api/v1/user/payments/giving-intents/{$intentId}/complete", [
            'proof_file_asset_id' => $proof->public_id,
        ])->assertOk()
            ->assertJsonPath('data.intent.status', 'succeeded')
            ->assertJsonPath('data.intent.purpose_code', 'event_payment');
    }

    private function enableLocalGiving(): void
    {
        config()->set('finance.governance_mode', 'allow_local');
        config()->set('finance.gateway', 'local_manual');
        config()->set('finance.allowed_purpose_codes', ['giving', 'event_payment']);
        DB::table('payment_provider_configurations')->update(['is_active' => false]);
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
