<?php

namespace Tests\Feature;

use App\Finance\PaymentProvider;
use App\Models\AuditEvent;
use App\Models\MinistryEvent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\GrantPermissionToRoleAction;
use App\Support\Authorization\ScopeReference;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminPaymentProviderApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_configure_and_activate_paystack_without_exposing_secrets(): void
    {
        $actor = $this->actorWithPermissions([
            'platform.payments.view',
            'platform.payments.manage',
        ], new ScopeReference('global', 'platform'));
        $this->authenticate($actor);

        $this->withHeaders($this->globalHeaders())
            ->getJson('/api/v1/admin/platform/payments')
            ->assertOk()
            ->assertJsonPath('data.configured', false)
            ->assertJsonPath('data.active', false);

        $this->withHeaders($this->globalHeaders())
            ->putJson('/api/v1/admin/platform/payments', [
                'active_provider' => PaymentProvider::Paystack->value,
                'paystack_secret_key' => 'sk_test_secret',
                'paystack_public_key' => 'pk_test_public',
                'paystack_webhook_secret' => 'whsec_test',
                'allowed_purpose_codes' => ['giving', 'event_payment'],
                'allowed_currencies' => ['NGN', 'USD'],
            ])
            ->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.active_provider', 'paystack')
            ->assertJsonPath('data.providers.paystack.credentials_configured', true)
            ->assertJsonMissing(['paystack_secret_key' => 'sk_test_secret'])
            ->assertJsonMissing(['paystack_public_key' => 'pk_test_public']);

        $raw = DB::table('payment_provider_configurations')->first();
        $this->assertNotNull($raw);
        $this->assertNotSame('sk_test_secret', $raw->paystack_secret_key);

        $this->withHeaders($this->globalHeaders())
            ->postJson('/api/v1/admin/platform/payments/activation')
            ->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.active_provider', 'paystack');

        $this->getJson('/api/v1/payments/configuration')
            ->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.provider', 'paystack')
            ->assertJsonPath('data.public_key', 'pk_test_public');

        $this->assertTrue(AuditEvent::query()->where('action', 'platform.payments.configured')->exists());
        $this->assertTrue(AuditEvent::query()->where('action', 'platform.payments.activated')->exists());
    }

    public function test_activating_paystack_requires_credentials(): void
    {
        $actor = $this->actorWithPermissions([
            'platform.payments.view',
            'platform.payments.manage',
        ], new ScopeReference('global', 'platform'));
        $this->authenticate($actor);

        $this->withHeaders($this->globalHeaders())
            ->putJson('/api/v1/admin/platform/payments', [
                'active_provider' => 'paystack',
            ])
            ->assertUnprocessable();
    }

    public function test_activated_paystack_starts_giving_checkout_and_verifies_webhook(): void
    {
        $actor = $this->actorWithPermissions([
            'platform.payments.view',
            'platform.payments.manage',
        ], new ScopeReference('global', 'platform'));
        $this->authenticate($actor);

        $this->withHeaders($this->globalHeaders())
            ->putJson('/api/v1/admin/platform/payments', [
                'active_provider' => 'paystack',
                'paystack_secret_key' => 'sk_test_secret',
                'paystack_public_key' => 'pk_test_public',
                'paystack_webhook_secret' => 'whsec_test',
            ])
            ->assertOk();
        $this->withHeaders($this->globalHeaders())
            ->postJson('/api/v1/admin/platform/payments/activation')
            ->assertOk();

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test-access',
                    'access_code' => 'access_test',
                    'reference' => 'will-be-replaced',
                ],
            ], 200),
        ]);

        $payer = User::factory()->withPerson()->create(['email' => 'giver@example.com']);
        $this->authenticate($payer);

        $create = $this->postJson('/api/v1/user/payments/giving-intents', [
            'amount_minor' => 500000,
            'currency' => 'NGN',
            'idempotency_key' => 'giving-paystack-0001',
        ])->assertCreated();

        $intentId = $create->json('data.id');
        $this->assertNotEmpty($intentId);
        $create->assertJsonPath('data.provider_code', 'paystack')
            ->assertJsonPath('data.client_payload.checkout_mode', 'redirect')
            ->assertJsonPath('data.client_payload.checkout_url', 'https://checkout.paystack.com/test-access')
            ->assertJsonPath('data.client_payload.public_key', 'pk_test_public');

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'id' => 9911,
                'reference' => $intentId,
                'amount' => 500000,
                'currency' => 'NGN',
                'status' => 'success',
                'paid_at' => now()->utc()->toIso8601String(),
                'metadata' => ['payment_intent_id' => $intentId],
            ],
        ];
        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha512', $raw, 'whsec_test');

        $this->call(
            'POST',
            '/api/v1/finance/webhooks/paystack',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
            ],
            $raw,
        )->assertCreated();

        $this->authenticate($payer);
        $this->getJson('/api/v1/user/payments/intents/'.$intentId)
            ->assertOk()
            ->assertJsonPath('data.status', 'succeeded');
    }

    public function test_activated_paystack_starts_event_fee_checkout(): void
    {
        $actor = $this->actorWithPermissions([
            'platform.payments.view',
            'platform.payments.manage',
        ], new ScopeReference('global', 'platform'));
        $this->authenticate($actor);

        $this->withHeaders($this->globalHeaders())
            ->putJson('/api/v1/admin/platform/payments', [
                'active_provider' => 'paystack',
                'paystack_secret_key' => 'sk_test_secret',
                'paystack_public_key' => 'pk_test_public',
                'paystack_webhook_secret' => 'whsec_test',
            ])
            ->assertOk();
        $this->withHeaders($this->globalHeaders())
            ->postJson('/api/v1/admin/platform/payments/activation')
            ->assertOk();

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/event-access',
                    'access_code' => 'event_access',
                    'reference' => 'event-ref',
                ],
            ], 200),
        ]);

        $event = MinistryEvent::factory()->published()->create([
            'fee_amount_minor' => 200000,
            'fee_currency' => 'NGN',
        ]);
        $payer = User::factory()->withPerson()->create(['email' => 'attendee@example.com']);
        $this->authenticate($payer);

        $register = $this->postJson('/api/v1/user/events/'.$event->public_id.'/registrations', [
            'idempotency_key' => 'event-register-pay-0001',
        ])->assertCreated();

        $this->assertSame('payment_pending', $register->json('data.status'));
        $registrationId = $register->json('data.id');

        $this->withHeaders(['X-Client-Channel' => 'mobile'])
            ->postJson('/api/v1/user/events/registrations/'.$registrationId.'/payment-intents', [
                'idempotency_key' => 'event-paystack-0001',
                'checkout_return' => 'mobile',
            ])
            ->assertCreated()
            ->assertJsonPath('data.provider_code', 'paystack')
            ->assertJsonPath('data.purpose_code', 'event_payment')
            ->assertJsonPath('data.amount_minor', 200000)
            ->assertJsonPath('data.client_payload.checkout_url', 'https://checkout.paystack.com/event-access');
    }

    public function test_payment_configuration_is_global_only(): void
    {
        $scope = new ScopeReference('country', '01JCOUNTRY0000000000000001');
        $actor = $this->actorWithPermissions(['platform.payments.view'], $scope);
        $this->authenticate($actor);

        $this->withHeaders(['X-Scope-Type' => $scope->type, 'X-Scope-ID' => $scope->key])
            ->getJson('/api/v1/admin/platform/payments')
            ->assertForbidden();
    }

    /** @param array<int, string> $permissionCodes */
    private function actorWithPermissions(array $permissionCodes, ScopeReference $scope): User
    {
        $actor = User::factory()->create();
        $role = Role::factory()->create();

        foreach ($permissionCodes as $permissionCode) {
            $permission = Permission::factory()->create(['code' => $permissionCode]);
            $this->app->make(GrantPermissionToRoleAction::class)->handle($role, $permission);
        }

        $assignment = $this->app->make(AssignRoleToUserAction::class)->handle($actor, $role);
        $this->app->make(AssignScopeToRoleAssignmentAction::class)->handle($assignment, $scope);

        return $actor;
    }

    private function authenticate(User $user): void
    {
        $securitySession = SecuritySession::factory()->for($user)->create();
        $this->actingAs($user);
        $this->withSession([
            'security_session_id' => $securitySession->public_id,
            'auth.mfa_verified_at' => now()->utc()->toIso8601String(),
        ]);
    }

    /** @return array<string, string> */
    private function globalHeaders(): array
    {
        return [
            'X-Scope-Type' => 'global',
            'X-Scope-ID' => 'platform',
        ];
    }
}
