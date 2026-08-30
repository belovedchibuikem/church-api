<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\Role;
use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use App\Support\Authorization\AuthorizationBundleCatalog;
use App\Support\Authorization\ProvisionAuthorizationBundlesAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MobileRegisterTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(ProvisionAuthorizationBundlesAction::class)->handle();
    }

    /** @return array<string, mixed> */
    private function registrationPayload(string $email = 'member@example.test'): array
    {
        return [
            'email' => $email,
            'password' => 'StrongPass!234',
            'password_confirmation' => 'StrongPass!234',
            'profile' => [
                'given_name' => 'Ada',
                'family_name' => 'Lovelace',
            ],
            'device_identifier' => 'test-installation-id',
            'device_label' => 'PHPUnit device',
            'device_type' => 'mobile',
            'platform' => 'android',
            'app_version' => '1.0.0',
        ];
    }

    public function test_valid_registration_creates_user_and_returns_mobile_credentials(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/mobile/auth/register', $this->registrationPayload());

        $user = User::query()->where('email', 'member@example.test')->sole();
        $response
            ->assertCreated()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.access_token', fn ($value) => is_string($value) && $value !== '')
            ->assertJsonPath('data.refresh_token', fn ($value) => is_string($value) && $value !== '')
            ->assertJsonPath('data.user.email', 'member@example.test')
            ->assertJsonPath('data.user.person_id', $user->person->public_id);

        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('Ada Lovelace', $user->name);
        $this->assertTrue(
            $user->roleAssignments()
                ->whereBelongsTo(Role::query()->where('code', AuthorizationBundleCatalog::MEMBER_SECURITY_ROLE)->sole())
                ->active()
                ->exists(),
        );
        Notification::assertSentTo($user, QueuedVerifyEmail::class);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->withPerson()->create(['email' => 'member@example.test']);

        $this->postJson('/api/v1/mobile/auth/register', $this->registrationPayload())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_registration_rejects_unknown_fields(): void
    {
        $payload = $this->registrationPayload();
        $payload['nickname'] = 'Ada';

        $this->postJson('/api/v1/mobile/auth/register', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields.request.0', 'The request contains unsupported fields.');
    }

    public function test_registered_user_can_sign_in_again_with_mobile_login(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/mobile/auth/register', $this->registrationPayload())
            ->assertCreated();

        $this->postJson('/api/v1/mobile/auth/login', [
            'email' => 'member@example.test',
            'password' => 'StrongPass!234',
            'device_identifier' => 'second-device',
            'device_label' => 'Second device',
            'device_type' => 'mobile',
            'platform' => 'ios',
            'app_version' => '1.0.0',
        ])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer');
    }
}
