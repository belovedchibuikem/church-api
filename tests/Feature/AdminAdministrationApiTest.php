<?php

namespace Tests\Feature;

use App\Administration\AdminWorkItemStatus;
use App\Identity\UserAccountStatus;
use App\Models\AdminWorkItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\AuthorizationBundleCatalog;
use App\Support\Authorization\GrantPermissionToRoleAction;
use App\Support\Authorization\ScopeReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminAdministrationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_create_requires_authentication(): void
    {
        $this->withHeaders($this->globalScopeHeaders())
            ->postJson('/api/v1/admin/users', [
                'email' => 'new.admin@example.test',
                'profile' => ['given_name' => 'Ada', 'family_name' => 'Lovelace'],
            ])
            ->assertUnauthorized();
    }

    public function test_forbidden_when_user_lacks_manage_permission(): void
    {
        $actor = $this->actorWithPermission('identity.users.view');
        $this->authenticate($actor);

        $this->withHeaders($this->globalScopeHeaders())
            ->postJson('/api/v1/admin/users', [
                'email' => 'new.admin@example.test',
                'profile' => ['given_name' => 'Ada', 'family_name' => 'Lovelace'],
            ])
            ->assertForbidden();
    }

    public function test_provisions_user_and_records_audit(): void
    {
        Notification::fake();
        $actor = $this->actorWithPermission('identity.users.manage');
        $this->authenticate($actor);

        $this->withHeaders($this->globalScopeHeaders())
            ->postJson('/api/v1/admin/users', [
                'email' => 'ada.lovelace@example.test',
                'profile' => ['given_name' => 'Ada', 'family_name' => 'Lovelace'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'ada.lovelace@example.test')
            ->assertJsonMissingPath('data.password');

        $this->assertDatabaseHas('users', ['email' => 'ada.lovelace@example.test']);
        $this->assertDatabaseHas('audit_events', ['action' => 'identity.user.provisioned']);
    }

    public function test_updates_user_name(): void
    {
        $actor = $this->actorWithPermission('identity.users.manage');
        $target = User::factory()->create(['name' => 'Old Name']);
        $this->authenticate($actor);

        $this->withHeaders($this->globalScopeHeaders())
            ->patchJson("/api/v1/admin/users/{$target->public_id}", ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');
    }

    public function test_last_super_administrator_cannot_be_suspended(): void
    {
        $role = Role::query()->firstOrCreate(
            ['code' => AuthorizationBundleCatalog::SUPER_ADMINISTRATOR_ROLE],
            ['name' => 'Super administrator'],
        );
        $super = User::factory()->create();
        $this->app->make(AssignRoleToUserAction::class)->handle($super, $role);
        $actor = $this->actorWithPermission('identity.users.suspend');
        $this->authenticate($actor);

        $this->withHeaders($this->globalScopeHeaders())
            ->postJson("/api/v1/admin/users/{$super->public_id}/suspension", ['reason' => 'security.review'])
            ->assertConflict();

        $this->assertSame(UserAccountStatus::Active, $super->fresh()->account_status);
    }

    public function test_creates_and_archives_custom_role(): void
    {
        $actor = $this->actorWithPermission('identity.roles.manage');
        $this->authenticate($actor);

        $created = $this->withHeaders($this->globalScopeHeaders())
            ->postJson('/api/v1/admin/access/roles', [
                'code' => 'custom.field.coordinator',
                'name' => 'Field coordinator',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'custom.field.coordinator');

        $id = $created->json('data.id');

        $this->withHeaders($this->globalScopeHeaders())
            ->deleteJson("/api/v1/admin/access/roles/{$id}")
            ->assertOk();

        $this->assertDatabaseMissing('roles', ['code' => 'custom.field.coordinator']);
    }

    public function test_system_role_delete_conflicts(): void
    {
        $actor = $this->actorWithPermission('identity.roles.manage');
        $role = Role::query()->firstOrCreate(
            ['code' => AuthorizationBundleCatalog::PLATFORM_ADMINISTRATOR_ROLE],
            ['name' => 'Platform identity and access administrator'],
        );
        $this->authenticate($actor);

        $this->withHeaders($this->globalScopeHeaders())
            ->deleteJson("/api/v1/admin/access/roles/{$role->public_id}")
            ->assertConflict();
    }

    public function test_work_item_crud_and_empty_list(): void
    {
        $actor = $this->actorWithPermission('administration.work_items.manage');
        $view = $this->actorWithPermission('administration.work_items.view');
        $this->authenticate($actor);

        $this->withHeaders($this->globalScopeHeaders())
            ->postJson('/api/v1/admin/administration/work-items', [
                'title' => 'Review access request',
                'priority' => 'high',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Review access request');

        $this->authenticate($view);
        $this->withHeaders($this->globalScopeHeaders())
            ->getJson('/api/v1/admin/administration/work-items?filter[status]=open')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1);

        $item = AdminWorkItem::query()->first();
        $this->authenticate($actor);
        $this->withHeaders($this->globalScopeHeaders())
            ->postJson("/api/v1/admin/administration/work-items/{$item->public_id}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', AdminWorkItemStatus::Archived->value);
    }

    public function test_work_items_forbidden_without_permission(): void
    {
        $actor = $this->actorWithPermission('identity.users.view');
        $this->authenticate($actor);

        $this->withHeaders($this->globalScopeHeaders())
            ->getJson('/api/v1/admin/administration/work-items')
            ->assertForbidden();
    }

    private function actorWithPermission(string $permissionCode): User
    {
        $actor = User::factory()->create();
        $role = Role::factory()->create();
        $permission = Permission::factory()->create(['code' => $permissionCode]);
        $this->app->make(GrantPermissionToRoleAction::class)->handle($role, $permission);
        $assignment = $this->app->make(AssignRoleToUserAction::class)->handle($actor, $role);
        $this->app->make(AssignScopeToRoleAssignmentAction::class)->handle($assignment, new ScopeReference('global', 'platform'));

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
    private function globalScopeHeaders(): array
    {
        return ['X-Scope-Type' => 'global', 'X-Scope-ID' => 'platform'];
    }
}
