<?php

namespace Tests\Feature;

use App\Models\ContentItem;
use App\Models\ContentPage;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\GrantPermissionToRoleAction;
use App\Support\Authorization\ScopeReference;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminContentCmsApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_update_and_delete_content_items(): void
    {
        $actor = $this->actorWithPermissions(['platform.configuration.manage'], new ScopeReference('global', 'platform'));
        $this->authenticate($actor);

        $page = new ContentPage;
        $page->forceFill([
            'slug' => 'home',
            'title' => 'Family House Connect',
            'summary' => 'Find community',
            'body' => 'Welcome',
            'locale' => 'en',
            'published_at' => now()->utc(),
        ])->save();

        $item = new ContentItem;
        $item->forceFill([
            'page_id' => $page->getKey(),
            'kind' => 'pillar',
            'title' => 'Church',
            'body' => 'Find community',
            'href' => '/church',
            'sort_order' => 0,
            'published_at' => now()->utc(),
        ])->save();

        $this->withHeaders($this->globalHeaders())
            ->putJson('/api/v1/admin/content/pages/'.$page->public_id.'/items/'.$item->public_id, [
                'title' => 'Local Church',
                'body' => 'Worship together worldwide.',
                'href' => '/find-church',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Local Church')
            ->assertJsonPath('data.body', 'Worship together worldwide.')
            ->assertJsonPath('data.href', '/find-church');

        $this->withHeaders($this->globalHeaders())
            ->deleteJson('/api/v1/admin/content/pages/'.$page->public_id.'/items/'.$item->public_id)
            ->assertOk()
            ->assertJsonPath('data.removed', true);

        $this->assertDatabaseMissing('content_items', ['id' => $item->getKey()]);
    }

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
