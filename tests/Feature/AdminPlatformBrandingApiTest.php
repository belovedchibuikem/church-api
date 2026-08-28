<?php

namespace Tests\Feature;

use App\Demo\DemoPngFactory;
use App\Models\AuditEvent;
use App\Models\FileAsset;
use App\Models\MfaMethod;
use App\Models\Permission;
use App\Models\PlatformBrandingConfiguration;
use App\Models\Role;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\GrantPermissionToRoleAction;
use App\Support\Authorization\ScopeReference;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminPlatformBrandingApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_public_branding_returns_defaults_before_configuration(): void
    {
        $this->getJson('/api/v1/branding')
            ->assertOk()
            ->assertJsonPath('data.app_name', 'Family House Connect')
            ->assertJsonPath('data.logo_url', null)
            ->assertJsonPath('data.favicon_url', null);

        $public = $this->getJson('/api/v1/branding')->json('data');
        $this->assertIsArray($public);
        $this->assertArrayNotHasKey('logo', $public);
    }

    public function test_admin_can_update_app_name_and_upload_logo_and_favicon_used_publicly(): void
    {
        Storage::fake('local');
        $actor = $this->actorWithPermissions([
            'platform.configuration.view',
            'platform.configuration.manage',
        ], new ScopeReference('global', 'platform'));
        $this->authenticate($actor);

        $this->withHeaders($this->globalHeaders())
            ->getJson('/api/v1/admin/platform/branding')
            ->assertOk()
            ->assertJsonPath('data.configured', false)
            ->assertJsonPath('data.logo_url', null);

        $this->withHeaders($this->globalHeaders())
            ->putJson('/api/v1/admin/platform/branding', ['app_name' => 'Kingdom House'])
            ->assertOk()
            ->assertJsonPath('data.app_name', 'Kingdom House')
            ->assertJsonPath('data.configured', true);

        $logo = UploadedFile::fake()->createWithContent('logo.png', DemoPngFactory::make([60, 30, 140], 64, 64));
        $this->withHeaders($this->globalHeaders() + ['Idempotency-Key' => 'branding-logo-test-key-01'])
            ->post('/api/v1/admin/platform/branding/logo', ['file' => $logo])
            ->assertOk()
            ->assertJsonPath('data.app_name', 'Kingdom House');

        $favicon = UploadedFile::fake()->createWithContent('favicon.png', DemoPngFactory::make([20, 80, 160], 32, 32));
        $this->withHeaders($this->globalHeaders() + ['Idempotency-Key' => 'branding-favicon-test-key-01'])
            ->post('/api/v1/admin/platform/branding/favicon', ['file' => $favicon])
            ->assertOk();

        $configuration = PlatformBrandingConfiguration::query()->with(['logoFile', 'faviconFile'])->firstOrFail();
        $this->assertNotNull($configuration->logoFile);
        $this->assertNotNull($configuration->faviconFile);
        $this->assertSame(1, FileAsset::query()->where('purpose', 'branding.logo')->count());
        $this->assertSame(1, FileAsset::query()->where('purpose', 'branding.favicon')->count());

        $logoUrl = rtrim((string) config('app.url'), '/').'/api/v1/media/'.$configuration->logoFile->public_id;
        $faviconUrl = rtrim((string) config('app.url'), '/').'/api/v1/media/'.$configuration->faviconFile->public_id;

        $this->getJson('/api/v1/branding')
            ->assertOk()
            ->assertJsonPath('data.app_name', 'Kingdom House')
            ->assertJsonPath('data.logo_url', $logoUrl)
            ->assertJsonPath('data.favicon_url', $faviconUrl);

        $this->get('/api/v1/media/'.$configuration->logoFile->public_id)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
        $this->get('/api/v1/media/'.$configuration->faviconFile->public_id)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->withHeaders($this->globalHeaders())
            ->deleteJson('/api/v1/admin/platform/branding/logo')
            ->assertOk()
            ->assertJsonPath('data.logo_url', null)
            ->assertJsonPath('data.favicon_url', $faviconUrl);

        $this->assertTrue(AuditEvent::query()->where('action', 'platform.branding.updated')->exists());
        $this->assertTrue(AuditEvent::query()->where('action', 'platform.branding.asset_uploaded')->exists());
        $this->assertTrue(AuditEvent::query()->where('action', 'platform.branding.asset_removed')->exists());
    }

    public function test_admin_without_enrolled_mfa_can_manage_branding(): void
    {
        $actor = $this->actorWithPermissions([
            'platform.configuration.view',
            'platform.configuration.manage',
        ], new ScopeReference('global', 'platform'));
        $this->authenticate($actor, withRecentMfa: false);

        $this->withHeaders($this->globalHeaders())
            ->putJson('/api/v1/admin/platform/branding', ['app_name' => 'House Without MFA'])
            ->assertOk()
            ->assertJsonPath('data.app_name', 'House Without MFA');
    }

    public function test_enrolled_mfa_still_required_for_branding_mutations(): void
    {
        $actor = $this->actorWithPermissions([
            'platform.configuration.manage',
        ], new ScopeReference('global', 'platform'));
        MfaMethod::factory()->for($actor)->create();
        $this->authenticate($actor, withRecentMfa: false);

        $this->withHeaders($this->globalHeaders())
            ->putJson('/api/v1/admin/platform/branding', ['app_name' => 'Blocked'])
            ->assertForbidden();
    }

    public function test_branding_mutations_require_global_platform_scope(): void
    {
        $scope = new ScopeReference('country', 'nigeria');
        $actor = $this->actorWithPermissions(['platform.configuration.manage'], $scope);
        $this->authenticate($actor);

        $this->withHeaders(['X-Scope-Type' => $scope->type, 'X-Scope-ID' => $scope->key])
            ->putJson('/api/v1/admin/platform/branding', ['app_name' => 'Hijack'])
            ->assertForbidden();
    }

    /** @param array<int, string> $permissionCodes */
    private function actorWithPermissions(array $permissionCodes, ScopeReference $scope): User
    {
        $actor = User::factory()->create();
        $role = Role::factory()->create();

        foreach ($permissionCodes as $permissionCode) {
            $permission = Permission::query()->firstOrCreate(['code' => $permissionCode]);
            $this->app->make(GrantPermissionToRoleAction::class)->handle($role, $permission);
        }

        $assignment = $this->app->make(AssignRoleToUserAction::class)->handle($actor, $role);
        $this->app->make(AssignScopeToRoleAssignmentAction::class)->handle($assignment, $scope);

        return $actor;
    }

    private function authenticate(User $user, bool $withRecentMfa = true): void
    {
        $securitySession = SecuritySession::factory()->for($user)->create();
        $this->actingAs($user);
        $session = ['security_session_id' => $securitySession->public_id];
        if ($withRecentMfa) {
            $session['auth.mfa_verified_at'] = now()->utc()->toIso8601String();
        }
        $this->withSession($session);
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
