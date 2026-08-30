<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MobileLoginTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array<string, mixed> */
    private function loginPayload(string $email = 'member@example.test', string $password = 'password'): array
    {
        return [
            'email' => $email,
            'password' => $password,
            'device_identifier' => 'test-installation-id',
            'device_label' => 'PHPUnit device',
            'device_type' => 'mobile',
            'platform' => 'android',
            'app_version' => '1.0.0',
        ];
    }

    public function test_verified_user_receives_mobile_credentials(): void
    {
        $user = User::factory()->withPerson()->create([
            'email' => 'member@example.test',
            'password' => 'password',
        ]);

        $this->postJson('/api/v1/mobile/auth/login', $this->loginPayload())
            ->assertOk()
            ->assertJsonPath('data.access_token', fn ($value) => is_string($value) && $value !== '')
            ->assertJsonPath('data.refresh_token', fn ($value) => is_string($value) && $value !== '')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonMissingPath('data.user.account_status');
    }

    public function test_invalid_credentials_return_401(): void
    {
        User::factory()->withPerson()->create([
            'email' => 'member@example.test',
            'password' => 'password',
        ]);

        $this->postJson('/api/v1/mobile/auth/login', $this->loginPayload(password: 'wrong-password'))
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'AUTH_UNAUTHENTICATED');
    }

    public function test_unverified_email_returns_actionable_validation_error(): void
    {
        User::factory()->withPerson()->unverified()->create([
            'email' => 'member@example.test',
            'password' => 'password',
        ]);

        $this->postJson('/api/v1/mobile/auth/login', $this->loginPayload())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath(
                'error.details.fields.email.0',
                'Verify your email address before using the mobile app. Open the verification link sent to your inbox, then try again.',
            );
    }
}
