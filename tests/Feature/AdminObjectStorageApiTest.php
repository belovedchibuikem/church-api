<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\ObjectStorageConfiguration;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SecuritySession;
use App\Models\User;
use App\Storage\Contracts\ObjectStorageConnectionValidator;
use App\Storage\Data\ObjectStorageValidationResult;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\GrantPermissionToRoleAction;
use App\Support\Authorization\ScopeReference;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminObjectStorageApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_global_administrator_can_configure_validate_activate_and_disable_optional_s3(): void
    {
        $actor = $this->actorWithPermissions([
            'platform.storage.view',
            'platform.storage.manage',
        ], new ScopeReference('global', 'platform'));
        $this->authenticate($actor);
        $this->app->instance(
            ObjectStorageConnectionValidator::class,
            new AdminObjectStorageValidatorStub(ObjectStorageValidationResult::succeeded()),
        );

        $this->withHeaders($this->globalHeaders())
            ->getJson('/api/v1/admin/platform/storage/object-storage')
            ->assertOk()
            ->assertJsonPath('data.configured', false)
            ->assertJsonPath('data.active_provider', 'local');

        $this->withHeaders($this->globalHeaders())
            ->putJson('/api/v1/admin/platform/storage/object-storage', [
                'access_key_id' => 'safe-access-key',
                'secret_access_key' => 'safe-secret-key',
                'region' => 'eu-west-1',
                'bucket' => 'family-house-connect-assets',
                'root_prefix' => 'production',
            ])
            ->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.credentials_configured', true)
            ->assertJsonMissing(['access_key_id' => 'safe-access-key'])
            ->assertJsonMissing(['secret_access_key' => 'safe-secret-key']);

        $configuration = ObjectStorageConfiguration::query()->firstOrFail();
        $raw = DB::table('object_storage_configurations')->where('id', $configuration->getKey())->first();
        $this->assertNotNull($raw);
        $this->assertNotSame('safe-access-key', $raw->access_key_id);
        $this->assertNotSame('safe-secret-key', $raw->secret_access_key);

        $this->withHeaders($this->globalHeaders())
            ->postJson('/api/v1/admin/platform/storage/object-storage/validation')
            ->assertOk()
            ->assertJsonPath('data.validation_result.status', 'succeeded');
        $this->withHeaders($this->globalHeaders())
            ->postJson('/api/v1/admin/platform/storage/object-storage/activation')
            ->assertOk()
            ->assertJsonPath('data.active', true);
        $this->withHeaders($this->globalHeaders())
            ->deleteJson('/api/v1/admin/platform/storage/object-storage/activation')
            ->assertOk()
            ->assertJsonPath('data.active_provider', 'local');

        $this->assertTrue(AuditEvent::query()->where('action', 'platform.object_storage.configured')->exists());
        $this->assertTrue(AuditEvent::query()->where('action', 'platform.object_storage.validation_succeeded')->exists());
        $this->assertTrue(AuditEvent::query()->where('action', 'platform.object_storage.activated')->exists());
        $this->assertTrue(AuditEvent::query()->where('action', 'platform.object_storage.deactivated')->exists());
    }

    public function test_private_or_loopback_endpoint_is_rejected_without_persisting_secrets(): void
    {
        $actor = $this->actorWithPermissions(
            ['platform.storage.manage'],
            new ScopeReference('global', 'platform'),
        );
        $this->authenticate($actor);

        $this->withHeaders($this->globalHeaders())
            ->putJson('/api/v1/admin/platform/storage/object-storage', [
                'access_key_id' => 'access-key',
                'secret_access_key' => 'secret-key',
                'region' => 'local-1',
                'bucket' => 'private-assets',
                'endpoint' => 'https://127.0.0.1',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'STORAGE_ENDPOINT_UNSAFE')
            ->assertJsonMissing(['secret-key']);

        $this->assertSame(0, ObjectStorageConfiguration::query()->count());
    }

    public function test_storage_configuration_is_global_only(): void
    {
        $scope = new ScopeReference('country', '01JCOUNTRY0000000000000001');
        $actor = $this->actorWithPermissions(['platform.storage.view'], $scope);
        $this->authenticate($actor);

        $this->withHeaders(['X-Scope-Type' => $scope->type, 'X-Scope-ID' => $scope->key])
            ->getJson('/api/v1/admin/platform/storage/object-storage')
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
        return ['X-Scope-Type' => 'global', 'X-Scope-ID' => 'platform'];
    }
}

final readonly class AdminObjectStorageValidatorStub implements ObjectStorageConnectionValidator
{
    public function __construct(private ObjectStorageValidationResult $result) {}

    public function validate(ObjectStorageConfiguration $configuration): ObjectStorageValidationResult
    {
        return $this->result;
    }
}
