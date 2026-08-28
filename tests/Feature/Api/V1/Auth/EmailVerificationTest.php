<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\AuditEvent;
use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_authenticated_user_can_request_verification_message(): void
    {
        Notification::fake();
        $user = User::factory()->withPerson()->unverified()->create(['email' => 'member@example.test']);
        $this->postJson('/api/v1/auth/login', [
            'email' => 'member@example.test',
            'password' => 'password',
        ])->assertOk();

        $response = $this->postJson('/api/v1/auth/email/verification-notification');

        $response
            ->assertAccepted()
            ->assertJsonPath('data.verification_request_accepted', true);
        Notification::assertSentTo($user, QueuedVerifyEmail::class);
    }

    public function test_valid_signed_link_verifies_active_account_idempotently(): void
    {
        $user = User::factory()->withPerson()->unverified()->create();
        $verificationUrl = $this->verificationUrl($user);

        $this->getJson($verificationUrl)->assertOk()->assertJsonPath('data.email_verified', true);
        $this->getJson($verificationUrl)->assertOk()->assertJsonPath('data.email_verified', true);

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertSame(1, AuditEvent::query()->where('action', 'identity.email.verified')->count());
    }

    public function test_tampered_verification_link_returns_403(): void
    {
        $user = User::factory()->withPerson()->unverified()->create();

        $this->getJson($this->verificationUrl($user).'tampered')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'AUTH_PERMISSION_DENIED');
        $this->assertNull($user->fresh()->email_verified_at);
    }

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'api.v1.auth.verification.verify',
            now()->addHour(),
            ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())],
        );
    }
}
