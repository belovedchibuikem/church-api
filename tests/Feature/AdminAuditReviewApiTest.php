<?php

namespace Tests\Feature;

use App\Models\AccessDecision;
use App\Models\AuditEvent;
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

class AdminAuditReviewApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_global_security_administrator_can_review_minimized_immutable_records(): void
    {
        $actor = $this->actorWithPermissions(['security.audit.view', 'security.access_decisions.view']);
        AuditEvent::factory()->create(['actor_user_id' => $actor->getKey(), 'action' => 'church.created', 'metadata' => ['secret' => 'not-returned']]);
        AccessDecision::factory()->for($actor, 'actor')->create(['permission_code' => 'church.churches.view', 'allowed' => false]);
        $this->authenticate($actor);
        $headers = ['X-Scope-Type' => 'global', 'X-Scope-ID' => 'platform'];

        $this->withHeaders($headers)->getJson('/api/v1/admin/security/audit-events?filter[action]=church.created')
            ->assertOk()->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.action', 'church.created')->assertJsonMissing(['secret' => 'not-returned']);

        $this->withHeaders($headers)->getJson('/api/v1/admin/security/access-decisions?filter[allowed]=0')
            ->assertOk()->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.permission_code', 'church.churches.view')
            ->assertJsonPath('data.0.allowed', false);
    }

    public function test_audit_event_show_returns_minimized_record_and_csv(): void
    {
        $actor = $this->actorWithPermissions(['security.audit.view']);
        $event = AuditEvent::factory()->create(['actor_user_id' => $actor->getKey(), 'action' => 'church.created', 'metadata' => ['secret' => 'hidden']]);
        $this->authenticate($actor);
        $headers = ['X-Scope-Type' => 'global', 'X-Scope-ID' => 'platform'];

        $this->withHeaders($headers)->getJson('/api/v1/admin/security/audit-events/'.$event->public_id)
            ->assertOk()
            ->assertJsonPath('data.id', $event->public_id)
            ->assertJsonPath('data.action', 'church.created')
            ->assertJsonMissing(['secret' => 'hidden']);

        $csv = $this->withHeaders($headers)->get('/api/v1/admin/security/audit-events/'.$event->public_id.'?format=csv');
        $csv->assertOk();
        $this->assertStringContainsString($event->public_id, $csv->streamedContent());
        $this->assertStringContainsString('attachment; filename=', (string) $csv->headers->get('content-disposition'));
    }

    public function test_non_global_scope_is_forbidden(): void
    {
        $scope = new ScopeReference('church', '01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $actor = $this->actorWithPermissions(['security.audit.view'], $scope);
        $this->authenticate($actor);

        $this->withHeaders(['X-Scope-Type' => $scope->type, 'X-Scope-ID' => $scope->key])
            ->getJson('/api/v1/admin/security/audit-events')->assertForbidden();
    }

    /** @param array<int, string> $permissionCodes */
    private function actorWithPermissions(array $permissionCodes, ?ScopeReference $scope = null): User
    {
        $actor = User::factory()->create();
        $role = Role::factory()->create();

        foreach ($permissionCodes as $code) {
            $permission = Permission::factory()->create(['code' => $code]);
            $this->app->make(GrantPermissionToRoleAction::class)->handle($role, $permission);
        }

        $assignment = $this->app->make(AssignRoleToUserAction::class)->handle($actor, $role);
        $this->app->make(AssignScopeToRoleAssignmentAction::class)->handle($assignment, $scope ?? new ScopeReference('global', 'platform'));

        return $actor;
    }

    private function authenticate(User $user): void
    {
        $session = SecuritySession::factory()->for($user)->create();
        $this->actingAs($user)->withSession([
            'security_session_id' => $session->public_id,
            'auth.mfa_verified_at' => now()->utc()->toIso8601String(),
        ]);
    }
}
