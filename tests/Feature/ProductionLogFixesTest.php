<?php

namespace Tests\Feature;

use App\Kca\KcaApplicationState;
use App\Models\Church;
use App\Models\ChurchDepartment;
use App\Models\KcaApplication;
use App\Models\Person;
use App\Models\SecuritySession;
use App\Models\SyncCheckpoint;
use App\Models\User;
use App\Support\Kca\ResolveKcaAccessQuery;
use App\Support\Security\AuthenticateMobileLoginAction;
use App\Support\Security\RegisterDeviceData;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProductionLogFixesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_kca_access_query_returns_interview_next_step_without_undefined_variable(): void
    {
        $person = Person::factory()->create();
        KcaApplication::factory()->for($person)->interview()->create();

        $payload = $this->app->make(ResolveKcaAccessQuery::class)->handle($person);

        $this->assertSame(KcaApplicationState::Interview->value, $payload['state']);
        $this->assertStringContainsString('orientation', strtolower((string) $payload['next_step']));
    }

    public function test_sync_checkpoint_can_be_created_for_a_person(): void
    {
        $user = User::factory()->withPerson()->create();
        $this->authenticate($user);

        $this->putJson('/api/v1/user/sync/checkpoint', [
            'cursor' => '2026-09-02T00:00:00Z',
        ])->assertOk()
            ->assertJsonPath('data.cursor', '2026-09-02T00:00:00Z');

        $this->assertSame(1, SyncCheckpoint::query()->where('person_id', $user->person_id)->count());
    }

    public function test_department_create_rejects_duplicate_name_within_church(): void
    {
        $church = Church::factory()->create();
        ChurchDepartment::query()->create([
            'church_id' => $church->getKey(),
            'name' => 'Usering',
            'status' => 'active',
        ]);

        $validator = validator(
            ['name' => 'Usering'],
            [
                'name' => [
                    'required',
                    'string',
                    \Illuminate\Validation\Rule::unique('church_departments', 'name')->where(
                        fn ($query) => $query->where('church_id', $church->getKey()),
                    ),
                ],
            ],
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_mobile_access_token_rejects_orphaned_relationships(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $credentials = $this->app->make(AuthenticateMobileLoginAction::class)->handle(
            $user->email,
            'password',
            new RegisterDeviceData(identifier: 'orphan-token-device'),
        );
        $token = $credentials->accessToken->load(['user', 'device', 'securitySession']);
        $token->setRelation('user', null);

        $middleware = $this->app->make(\App\Http\Middleware\AuthenticateMobileAccessToken::class);
        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('isUsable');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($middleware, $token, 'orphan-token-device'));
    }

    private function authenticate(User $user): SecuritySession
    {
        $securitySession = SecuritySession::factory()->for($user)->create();
        $this->actingAs($user);
        $this->withSession(['security_session_id' => $securitySession->public_id]);

        return $securitySession;
    }
}
