<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Church;
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
use Tests\TestCase;

class AdminMapsProviderApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_configure_any_provider_key_and_activate_leaflet_without_key(): void
    {
        $actor = $this->actorWithPermissions([
            'platform.maps.view',
            'platform.maps.manage',
        ], new ScopeReference('global', 'platform'));
        $this->authenticate($actor);

        $this->withHeaders($this->globalHeaders())
            ->getJson('/api/v1/admin/platform/maps')
            ->assertOk()
            ->assertJsonPath('data.configured', false)
            ->assertJsonPath('data.active', false);

        $this->withHeaders($this->globalHeaders())
            ->putJson('/api/v1/admin/platform/maps', [
                'active_provider' => 'leaflet',
                'google_api_key' => 'test-google-key',
                'mapbox_access_token' => 'test-mapbox-token',
                'default_latitude' => 6.5244,
                'default_longitude' => 3.3792,
                'default_zoom' => 11,
            ])
            ->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.active_provider', 'leaflet')
            ->assertJsonPath('data.providers.google.key_configured', true)
            ->assertJsonPath('data.providers.mapbox.key_configured', true)
            ->assertJsonMissing(['google_api_key' => 'test-google-key'])
            ->assertJsonMissing(['mapbox_access_token' => 'test-mapbox-token']);

        $raw = DB::table('maps_provider_configurations')->first();
        $this->assertNotNull($raw);
        $this->assertNotSame('test-google-key', $raw->google_api_key);
        $this->assertNotSame('test-mapbox-token', $raw->mapbox_access_token);

        $this->withHeaders($this->globalHeaders())
            ->postJson('/api/v1/admin/platform/maps/activation')
            ->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.active_provider', 'leaflet');

        $this->getJson('/api/v1/maps/configuration')
            ->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.provider', 'leaflet')
            ->assertJsonPath('data.client_api_key', null);

        $this->assertTrue(AuditEvent::query()->where('action', 'platform.maps.configured')->exists());
        $this->assertTrue(AuditEvent::query()->where('action', 'platform.maps.activated')->exists());
    }

    public function test_activating_google_requires_key_then_exposes_client_key_publicly(): void
    {
        $actor = $this->actorWithPermissions([
            'platform.maps.view',
            'platform.maps.manage',
        ], new ScopeReference('global', 'platform'));
        $this->authenticate($actor);

        $this->withHeaders($this->globalHeaders())
            ->putJson('/api/v1/admin/platform/maps', [
                'active_provider' => 'google',
            ])
            ->assertUnprocessable();

        $this->withHeaders($this->globalHeaders())
            ->putJson('/api/v1/admin/platform/maps', [
                'active_provider' => 'google',
                'google_api_key' => 'browser-google-maps-key',
            ])
            ->assertOk();

        $this->withHeaders($this->globalHeaders())
            ->postJson('/api/v1/admin/platform/maps/activation')
            ->assertOk()
            ->assertJsonPath('data.active_provider', 'google');

        $this->getJson('/api/v1/maps/configuration')
            ->assertOk()
            ->assertJsonPath('data.provider', 'google')
            ->assertJsonPath('data.client_api_key', 'browser-google-maps-key');
    }

    public function test_places_endpoint_returns_geocoded_churches(): void
    {
        $church = Church::factory()->published()->create([
            'name' => 'Family House Ikeja',
        ]);
        $church->location->update([
            'latitude' => 6.6018,
            'longitude' => 3.3515,
        ]);

        $this->getJson('/api/v1/maps/places?type=church')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Family House Ikeja')
            ->assertJsonPath('data.0.coordinates.latitude', 6.6018);
    }

    public function test_maps_configuration_is_global_only(): void
    {
        $scope = new ScopeReference('country', '01JCOUNTRY0000000000000001');
        $actor = $this->actorWithPermissions(['platform.maps.view'], $scope);
        $this->authenticate($actor);

        $this->withHeaders(['X-Scope-Type' => $scope->type, 'X-Scope-ID' => $scope->key])
            ->getJson('/api/v1/admin/platform/maps')
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
