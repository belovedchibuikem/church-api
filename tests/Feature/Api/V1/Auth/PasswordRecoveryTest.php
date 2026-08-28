<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\AuditEvent;
use App\Models\SecuritySession;
use App\Models\User;
use App\Notifications\QueuedResetPassword;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_existing_and_unknown_accounts_receive_same_recovery_response(): void
    {
        Notification::fake();
        $user = User::factory()->withPerson()->create(['email' => 'member@example.test']);

        $existingResponse = $this->postJson('/api/v1/auth/password/forgot', [
            'email' => 'member@example.test',
        ]);
        $unknownResponse = $this->postJson('/api/v1/auth/password/forgot', [
            'email' => 'unknown@example.test',
        ]);

        foreach ([$existingResponse, $unknownResponse] as $response) {
            $response
                ->assertAccepted()
                ->assertJsonPath('data.password_reset_request_accepted', true);
        }
        Notification::assertSentTo($user, QueuedResetPassword::class);
        Notification::assertCount(1);
    }

    public function test_valid_reset_changes_password_and_revokes_security_sessions(): void
    {
        $user = User::factory()->withPerson()->create(['email' => 'member@example.test']);
        $securitySession = SecuritySession::factory()->for($user)->create();
        $token = Password::broker()->createToken($user);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'email' => 'member@example.test',
            'token' => $token,
            'password' => 'NewStrongPass!234',
            'password_confirmation' => 'NewStrongPass!234',
        ]);

        $response->assertOk()->assertJsonPath('data.password_reset', true);
        $this->assertTrue(Hash::check('NewStrongPass!234', $user->fresh()->password));
        $this->assertSame('password.reset', $securitySession->fresh()->revocation_reason);
        $this->assertSame(1, AuditEvent::query()->where('action', 'identity.password.reset')->count());
    }

    public function test_invalid_reset_returns_generic_422_without_changing_password(): void
    {
        $user = User::factory()->withPerson()->create(['email' => 'member@example.test']);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'email' => 'member@example.test',
            'token' => 'invalid-token',
            'password' => 'NewStrongPass!234',
            'password_confirmation' => 'NewStrongPass!234',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.details.fields.token.0',
                'The password reset request is invalid or has expired.',
            );
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
