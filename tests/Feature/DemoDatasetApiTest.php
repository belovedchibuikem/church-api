<?php

namespace Tests\Feature;

use App\Demo\SeedDemoDatasetAction;
use App\Demo\WipeDemoDatasetAction;
use App\Models\Church;
use App\Models\DemoDataset;
use App\Models\MinistryEvent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\GrantPermissionToRoleAction;
use App\Support\Authorization\ScopeReference;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoDatasetApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_seeds_live_demonstration_rows_and_admin_can_wipe_them(): void
    {
        Storage::fake('local');
        $result = $this->app->make(SeedDemoDatasetAction::class)->handle();

        $this->assertTrue($result['seeded']);
        $this->assertTrue(DemoDataset::query()->where('dataset_key', DemoDataset::KEY)->exists());
        $this->assertGreaterThanOrEqual(6, Church::query()->whereNotNull('published_at')->count());
        $this->assertGreaterThanOrEqual(5, MinistryEvent::query()->whereNotNull('published_at')->count());
        $this->assertTrue(User::query()->where('email', 'admin@familyhouse.demo')->exists());

        $response = $this->getJson('/api/v1/churches?per_page=20');
        $response->assertOk();
        $this->assertGreaterThanOrEqual(6, (int) $response->json('meta.pagination.total'));

        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions([
            'platform.configuration.view',
            'platform.configuration.manage',
        ], $scope);
        $this->authenticate($actor);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/admin/platform/demo')
            ->assertOk()
            ->assertJsonPath('data.seeded', true)
            ->assertJsonPath('data.password_hint', SeedDemoDatasetAction::PASSWORD);

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/admin/platform/demo/wipe', ['confirmation' => 'NOPE'])
            ->assertUnprocessable();

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/admin/platform/demo/wipe', ['confirmation' => 'ERASE DEMO'])
            ->assertOk()
            ->assertJsonPath('data.wiped', true);

        $this->assertFalse(DemoDataset::query()->where('dataset_key', DemoDataset::KEY)->exists());
        $this->assertSame(0, Church::query()->where('name', 'Family House Church Ikeja')->count());
    }

    public function test_wipe_without_a_dataset_is_a_no_op(): void
    {
        $result = $this->app->make(WipeDemoDatasetAction::class)->handle();
        $this->assertFalse($result['wiped']);
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
    private function headers(): array
    {
        return ['X-Scope-Type' => 'global', 'X-Scope-ID' => 'platform'];
    }
}
