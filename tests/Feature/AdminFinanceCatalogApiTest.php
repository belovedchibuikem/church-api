<?php

namespace Tests\Feature;

use App\Finance\PaymentIntentStatus;
use App\Finance\PaymentReconciliationStatus;
use App\Models\PaymentIntent;
use App\Models\PaymentReconciliation;
use App\Models\PaymentTransaction;
use App\Models\Permission;
use App\Models\Person;
use App\Models\Role;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\GrantPermissionToRoleAction;
use App\Support\Authorization\ScopeReference;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminFinanceCatalogApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_transaction_catalog_uses_intent_status_and_provider_channel(): void
    {
        $actor = $this->actorWithPermissions(['finance.payment_transactions.view']);
        $person = Person::factory()->withProfile()->create();
        $person->profile?->update(['given_name' => 'Samuel', 'family_name' => 'Donor']);
        $tithe = PaymentIntent::factory()->create([
            'payer_person_id' => $person->getKey(),
            'purpose_code' => 'tithe',
            'status' => PaymentIntentStatus::Succeeded,
            'amount_minor' => 5000000,
            'currency' => 'NGN',
        ]);
        $offering = PaymentIntent::factory()->create([
            'purpose_code' => 'offering',
            'status' => PaymentIntentStatus::PendingProvider,
        ]);
        $titheTx = PaymentTransaction::factory()->create([
            'payment_intent_id' => $tithe->getKey(),
            'provider_code' => 'paystack',
            'amount_minor' => 5000000,
            'currency' => 'NGN',
        ]);
        PaymentTransaction::factory()->create([
            'payment_intent_id' => $offering->getKey(),
            'provider_code' => 'flutterwave',
        ]);
        PaymentReconciliation::factory()->create([
            'payment_transaction_id' => $titheTx->getKey(),
            'status' => PaymentReconciliationStatus::Matched,
        ]);

        $this->authenticate($actor);
        $headers = ['X-Scope-Type' => 'global', 'X-Scope-ID' => 'platform'];

        $listed = $this->withHeaders($headers)
            ->getJson('/api/v1/admin/catalog/finance/payment-transactions')
            ->assertOk();

        $titheRow = collect($listed->json('data'))->firstWhere('id', $titheTx->public_id);
        $this->assertIsArray($titheRow);
        $this->assertSame('paystack', $titheRow['channel']);
        $this->assertSame('tithe', $titheRow['category']);
        $this->assertSame('succeeded', $titheRow['status']);
        $this->assertSame('matched', $titheRow['reconciliation_status']);
        $this->assertNotSame($titheRow['channel'], $titheRow['category']);

        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/catalog/finance/payment-transactions?filter[purpose]=tithe')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', $titheTx->public_id);

        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/catalog/finance/payment-transactions?filter[status]=succeeded')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.status', 'succeeded');

        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/catalog/finance/payment-transactions?filter[search]=Samuel')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.donor_name', 'Samuel Donor');

        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/finance/payment-transactions/'.$titheTx->public_id)
            ->assertOk()
            ->assertJsonPath('data.status', 'succeeded')
            ->assertJsonPath('data.channel', 'paystack')
            ->assertJsonPath('data.reconciliation_status', 'matched');
    }

    /** @param array<int, string> $permissionCodes */
    private function actorWithPermissions(array $permissionCodes, ?ScopeReference $scope = null): User
    {
        $actor = User::factory()->create();
        $role = Role::factory()->create();

        foreach ($permissionCodes as $code) {
            $permission = Permission::factory()->create(['code' => $code]);
            $this->app->make(GrantPermissionToRoleAction::class)->handle($role, $permission);
        }

        $assignment = $this->app->make(AssignRoleToUserAction::class)->handle($actor, $role);
        $this->app->make(AssignScopeToRoleAssignmentAction::class)->handle(
            $assignment,
            $scope ?? new ScopeReference('global', 'platform'),
        );

        return $actor;
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
