<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Country;
use App\Models\Permission;
use App\Models\PlatformConfiguration;
use App\Models\Role;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\GrantPermissionToRoleAction;
use App\Support\Authorization\ScopeReference;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminPlatformSettingsApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_confidential_configuration_is_encrypted_redacted_and_audited(): void
    {
        $actor = $this->actorWithPermissions([
            'platform.configuration.view',
            'platform.configuration.manage',
        ], new ScopeReference('global', 'platform'));
        $this->authenticate($actor);

        $this->withHeaders($this->globalHeaders())
            ->putJson('/api/v1/admin/platform/configurations', [
                'key' => 'communications.provider.secret',
                'value_type' => 'string',
                'classification' => 'confidential',
                'value' => 'never-return-this-secret',
                'environment' => 'production',
            ])
            ->assertOk()
            ->assertJsonPath('data.classification', 'confidential')
            ->assertJsonPath('data.value', null)
            ->assertJsonMissing(['value' => 'never-return-this-secret']);

        $configuration = PlatformConfiguration::query()->where('key', 'communications.provider.secret')->firstOrFail();
        $this->assertNotSame('never-return-this-secret', $configuration->getRawOriginal('stored_value'));
        $this->assertTrue(AuditEvent::query()->where('action', 'platform.configuration.created')->exists());

        $this->withHeaders($this->globalHeaders())
            ->getJson('/api/v1/admin/platform/configurations?filter[environment]=production')
            ->assertOk()
            ->assertJsonPath('data.0.value', null)
            ->assertJsonMissing(['value' => 'never-return-this-secret']);
    }

    public function test_internal_configuration_and_feature_flag_support_scoped_lifecycle(): void
    {
        $country = Country::factory()->create();
        $scope = new ScopeReference('country', $country->public_id);
        $actor = $this->actorWithPermissions([
            'platform.configuration.view',
            'platform.configuration.manage',
            'platform.feature_flags.view',
            'platform.feature_flags.manage',
        ], $scope);
        $this->authenticate($actor);
        $headers = ['X-Scope-Type' => $scope->type, 'X-Scope-ID' => $scope->key];

        $this->withHeaders($headers)
            ->putJson('/api/v1/admin/platform/configurations', [
                'key' => 'church.first_timer.follow_up_hours',
                'value_type' => 'integer',
                'classification' => 'internal',
                'value' => 48,
                'environment' => 'production',
                'scope_type' => $scope->type,
                'scope_id' => $scope->key,
            ])
            ->assertOk()
            ->assertJsonPath('data.value', 48)
            ->assertJsonPath('data.scope.id', $scope->key);

        $flagId = $this->withHeaders($headers)
            ->putJson('/api/v1/admin/platform/feature-flags', [
                'key' => 'church.follow_up_alerts',
                'environment' => 'production',
                'scope_type' => $scope->type,
                'scope_id' => $scope->key,
                'rollout_percentage' => 25,
            ])
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->json('data.id');

        $this->withHeaders($headers)
            ->postJson("/api/v1/admin/platform/feature-flags/{$flagId}/enabled")
            ->assertOk()
            ->assertJsonPath('data.enabled', true);
        $this->withHeaders($headers)
            ->deleteJson("/api/v1/admin/platform/feature-flags/{$flagId}/enabled")
            ->assertOk()
            ->assertJsonPath('data.enabled', false);

        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/platform/feature-flags')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.rollout_percentage', 25);

        $this->assertTrue(AuditEvent::query()->where('action', 'platform.feature_flag.enabled')->exists());
        $this->assertTrue(AuditEvent::query()->where('action', 'platform.feature_flag.disabled')->exists());
    }

    public function test_scoped_administrator_cannot_write_another_scope(): void
    {
        $allowedCountry = Country::factory()->create();
        $deniedCountry = Country::factory()->create();
        $scope = new ScopeReference('country', $allowedCountry->public_id);
        $actor = $this->actorWithPermissions(['platform.configuration.manage'], $scope);
        $this->authenticate($actor);

        $this->withHeaders(['X-Scope-Type' => $scope->type, 'X-Scope-ID' => $scope->key])
            ->putJson('/api/v1/admin/platform/configurations', [
                'key' => 'church.testing.value',
                'value_type' => 'boolean',
                'classification' => 'internal',
                'value' => true,
                'environment' => 'production',
                'scope_type' => 'country',
                'scope_id' => $deniedCountry->public_id,
            ])
            ->assertNotFound();
    }

    public function test_missing_permission_is_denied(): void
    {
        $actor = User::factory()->create();
        $this->authenticate($actor);

        $this->withHeaders($this->globalHeaders())
            ->getJson('/api/v1/admin/platform/configurations')
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
