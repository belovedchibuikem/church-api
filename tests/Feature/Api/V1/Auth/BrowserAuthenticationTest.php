<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\SecuritySession;
use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BrowserAuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_valid_registration_creates_identity_and_browser_security_session(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', $this->registrationPayload());

        $user = User::query()->where('email', 'member@example.test')->sole();
        $response
            ->assertCreated()
            ->assertJsonPath('data.person_id', $user->person->public_id)
            ->assertJsonPath('data.email', 'member@example.test')
            ->assertJsonPath('data.profile.given_name', 'Ada')
            ->assertSessionHas('security_session_id');
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertTrue(Hash::check('StrongPass!234', $user->password));
        $this->assertSame('Lovelace', $user->person->profile->family_name);
        $this->assertSame(1, SecuritySession::query()->whereBelongsTo($user)->usable()->count());
        Notification::assertSentTo($user, QueuedVerifyEmail::class);
    }

    public function test_registration_persists_optional_profile_location(): void
    {
        Notification::fake();

        $payload = $this->registrationPayload();
        $payload['profile']['country'] = 'ng';
        $payload['profile']['region'] = 'Lagos State';
        $payload['profile']['locality'] = 'Ikeja';

        $this->postJson('/api/v1/auth/register', $payload)
            ->assertCreated()
            ->assertJsonPath('data.profile.country', 'NG')
            ->assertJsonPath('data.profile.region', 'Lagos State')
            ->assertJsonPath('data.profile.locality', 'Ikeja');

        $user = User::query()->where('email', 'member@example.test')->sole();
        $this->assertSame('NG', $user->person->profile->country);
        $this->assertSame('Lagos State', $user->person->profile->region);
        $this->assertSame('Ikeja', $user->person->profile->locality);
    }

    public function test_registration_rejects_unknown_fields(): void
    {
        $payload = $this->registrationPayload();
        $payload['nickname'] = 'Ada';

        $this->postJson('/api/v1/auth/register', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields.request.0', 'The request contains unsupported fields.');
    }

    public function test_registration_rejects_unknown_profile_fields(): void
    {
        $payload = $this->registrationPayload();
        $payload['profile']['nickname'] = 'Ada';

        $this->postJson('/api/v1/auth/register', $payload)
            ->assertUnprocessable();
    }

    public function test_invalid_and_suspended_credentials_return_the_same_generic_422(): void
    {
        User::factory()->withPerson()->suspended()->create(['email' => 'suspended@example.test']);

        $invalidResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'missing@example.test',
            'password' => 'wrong-password',
        ]);
        $suspendedResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'suspended@example.test',
            'password' => 'password',
        ]);

        foreach ([$invalidResponse, $suspendedResponse] as $response) {
            $response
                ->assertUnprocessable()
                ->assertJsonPath('error.details.fields.email.0', 'The provided credentials are invalid.');
        }
    }

    public function test_active_users_can_sign_in_with_special_character_passwords(): void
    {
        $user = User::factory()->withPerson()->create([
            'email' => 'member@example.test',
            'password' => 'StrongPass!234',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'member@example.test',
            'password' => 'StrongPass!234',
            'remember' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.email', 'member@example.test');

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_verified_current_user_projection_and_logout_are_session_bound(): void
    {
        $user = User::factory()->withPerson()->create(['email' => 'member@example.test']);
        $this->postJson('/api/v1/auth/login', [
            'email' => 'member@example.test',
            'password' => 'password',
        ])->assertOk();

        $this->getJson('/api/v1/user/me')
            ->assertOk()
            ->assertJsonPath('data.person_id', $user->person->public_id)
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.account_status');

        $securitySession = SecuritySession::query()->whereBelongsTo($user)->sole();
        $this->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('data.authenticated', false);
        $this->assertGuest('web');
        $this->assertSame('browser.logout', $securitySession->fresh()->revocation_reason);
    }

    public function test_unverified_current_user_is_rejected_with_403(): void
    {
        User::factory()->withPerson()->unverified()->create(['email' => 'member@example.test']);
        $this->postJson('/api/v1/auth/login', [
            'email' => 'member@example.test',
            'password' => 'password',
        ])->assertOk();

        $this->getJson('/api/v1/user/me')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'AUTH_PERMISSION_DENIED');
    }

    public function test_credentialed_browser_requests_echo_the_frontend_origin(): void
    {
        $origin = 'http://localhost:3000';

        $this->getJson('/api/v1/auth/csrf-cookie', ['Origin' => $origin])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertHeader('Access-Control-Allow-Credentials', 'true')
            ->assertJsonPath('data.csrf_cookie', true);

        $token = $this->getJson('/api/v1/auth/csrf-cookie')->json('data.csrf_token');
        $this->assertIsString($token);
        $this->assertNotSame('', $token);

        $this->call('OPTIONS', '/api/v1/auth/login', [], [], [], [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,x-xsrf-token',
        ])
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_flutter_web_loopback_origins_are_allowed_for_mobile_login_preflight(): void
    {
        $origin = 'http://localhost:62155';

        $this->call('OPTIONS', '/api/v1/mobile/auth/login', [], [], [], [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,accept',
        ])
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', $origin);
    }

    /** @return array<string, mixed> */
    private function registrationPayload(): array
    {
        return [
            'profile' => [
                'given_name' => 'Ada',
                'middle_name' => null,
                'family_name' => 'Lovelace',
                'preferred_name' => null,
            ],
            'email' => 'MEMBER@example.test',
            'password' => 'StrongPass!234',
            'password_confirmation' => 'StrongPass!234',
        ];
    }
}
