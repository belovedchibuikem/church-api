<?php

namespace Tests\Feature;

use App\AdvisoryAi\Assistant;
use App\AdvisoryAi\UseCase;
use App\Communication\CommunicationChannel;
use App\Files\FileAssetStatus;
use App\Models\Church;
use App\Models\FileAsset;
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

class AdminRemainingGapsApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_platform_search_finds_a_created_church_by_name(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['platform.search.query'], $scope);
        $this->authenticate($actor);
        $church = Church::factory()->create(['name' => 'Unique Searchable Chapel']);

        $this->withHeaders($this->headers($scope))
            ->postJson('/api/v1/admin/platform/search/queries', [
                'term' => 'Searchable Chapel',
                'resource_types' => ['church'],
            ])
            ->assertOk()
            ->assertJsonPath('data.0.resource_type', 'church')
            ->assertJsonPath('data.0.resource_id', $church->public_id)
            ->assertJsonPath('data.0.title', 'Unique Searchable Chapel')
            ->assertJsonPath('data.0.classification', 'public');
    }

    public function test_advisory_returns_unavailable_when_provider_is_disabled(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['platform.advisory.request'], $scope);
        $this->authenticate($actor);

        $this->withHeaders($this->headers($scope))
            ->postJson('/api/v1/admin/platform/advisory/requests', [
                'assistant' => Assistant::Mission->value,
                'use_case' => UseCase::FollowUpGapDetection->value,
                'instruction' => 'Find follow-up gaps.',
            ])
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.reason_code', 'provider_disabled')
            ->assertJsonPath('data.requires_human_decision', true);
    }

    public function test_kca_operator_can_create_a_year(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.years.manage'], $scope);
        $this->authenticate($actor);

        $this->withHeaders($this->headers($scope))
            ->postJson('/api/v1/admin/kca/years', [
                'code' => 'year-2026-gaps',
                'name' => '2026 KCA Year',
                'starts_on' => '2026-01-01',
                'ends_on' => '2026-12-31',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'year-2026-gaps')
            ->assertJsonPath('data.name', '2026 KCA Year');
    }

    public function test_events_operator_can_create_an_event(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['events.events.manage'], $scope);
        $this->authenticate($actor);

        $this->withHeaders($this->headers($scope))
            ->postJson('/api/v1/admin/events', [
                'category_code' => 'training',
                'name' => 'Leaders Retreat',
                'starts_at' => now()->addWeek()->utc()->toIso8601String(),
                'ends_at' => now()->addWeek()->addDay()->utc()->toIso8601String(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Leaders Retreat')
            ->assertJsonPath('data.category_code', 'training');
    }

    public function test_platform_admin_can_approve_a_quarantined_file_asset(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['platform.files.approve'], $scope);
        $this->authenticate($actor);
        $asset = FileAsset::factory()->create(['status' => FileAssetStatus::Quarantined]);

        $this->withHeaders($this->headers($scope))
            ->postJson("/api/v1/admin/platform/files/{$asset->public_id}/approval")
            ->assertOk()
            ->assertJsonPath('data.id', $asset->public_id)
            ->assertJsonPath('data.status', FileAssetStatus::Available->value)
            ->assertJsonPath('data.malware_scan_status', 'clean');
    }

    public function test_platform_admin_can_download_available_file_content(): void
    {
        Storage::fake('local');
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['platform.files.view'], $scope);
        $this->authenticate($actor);
        $contents = 'admin visible document';
        $asset = FileAsset::factory()->available()->create([
            'detected_mime_type' => 'text/plain',
            'byte_size' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'metadata' => ['original_filename' => 'admin-notes.txt'],
        ]);
        Storage::disk('local')->put($asset->object_key, $contents);

        $response = $this->withHeaders($this->headers($scope))
            ->get("/api/v1/admin/platform/files/{$asset->public_id}/content");

        $response
            ->assertOk()
            ->assertDownload('admin-notes.txt');
        $this->assertSame($contents, $response->streamedContent());
    }

    public function test_communications_operator_can_create_a_template(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['communications.templates.manage'], $scope);
        $this->authenticate($actor);

        $this->withHeaders($this->headers($scope))
            ->postJson('/api/v1/admin/communications/templates', [
                'code' => 'welcome.member',
                'channel' => CommunicationChannel::Email->value,
                'locale' => 'en',
                'subject' => 'Welcome',
                'body' => 'Welcome to Family House.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'welcome.member')
            ->assertJsonPath('data.channel', 'email')
            ->assertJsonPath('data.subject', 'Welcome');
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
    private function headers(ScopeReference $scope): array
    {
        return ['X-Scope-Type' => $scope->type, 'X-Scope-ID' => $scope->key];
    }
}
