<?php

namespace Tests\Feature;

use App\Demo\DemoPngFactory;
use App\Files\FileAssetClassification;
use App\Media\MediaRole;
use App\Models\Church;
use App\Models\FileAsset;
use App\Models\MediaAttachment;
use App\Models\Permission;
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

class AdminMediaOperationsApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_attach_replace_and_remove_a_church_cover_image(): void
    {
        Storage::fake('local');
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['platform.files.manage'], $scope);
        $this->authenticate($actor);
        $church = Church::factory()->published()->create(['name' => 'Demo Cover Church']);
        $bytes = DemoPngFactory::make([10, 27, 53], 48, 32);
        $asset = FileAsset::factory()->available()->create([
            'classification' => FileAssetClassification::Public,
            'purpose' => 'media.public',
            'detected_mime_type' => 'image/png',
            'byte_size' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
        ]);
        Storage::disk('local')->put($asset->object_key, $bytes);

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/admin/platform/media', [
                'attachable_type' => 'church',
                'attachable_id' => $church->public_id,
                'file_asset_id' => $asset->public_id,
                'role' => MediaRole::Cover->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'cover')
            ->assertJsonPath('data.attachable.type', 'church')
            ->assertJsonPath('data.file.id', $asset->public_id);

        $this->getJson("/api/v1/churches/{$church->public_id}")
            ->assertOk()
            ->assertJsonPath('data.image_url', rtrim((string) config('app.url'), '/').'/api/v1/media/'.$asset->public_id);

        $attachmentId = MediaAttachment::query()->value('public_id');
        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/admin/platform/media/{$attachmentId}")
            ->assertOk()
            ->assertJsonPath('data.removed', true);

        $this->getJson("/api/v1/churches/{$church->public_id}")
            ->assertOk()
            ->assertJsonPath('data.image_url', null);
    }

    public function test_admin_can_upload_a_public_image_and_attach_it_in_one_step(): void
    {
        Storage::fake('local');
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['platform.files.manage'], $scope);
        $this->authenticate($actor);
        $church = Church::factory()->published()->create();
        $bytes = DemoPngFactory::make([72, 48, 160], 80, 60);
        $upload = UploadedFile::fake()->createWithContent('campus.png', $bytes);

        $this->withHeaders($this->headers() + ['Idempotency-Key' => 'media-upload-test-key-01'])
            ->post('/api/v1/admin/platform/media/uploads', [
                'file' => $upload,
                'attachable_type' => 'church',
                'attachable_id' => $church->public_id,
                'role' => 'hero',
                'purpose' => 'media.public',
                'classification' => 'public',
            ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'hero')
            ->assertJsonPath('data.attachable.id', $church->public_id);
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
    private function headers(): array
    {
        return ['X-Scope-Type' => 'global', 'X-Scope-ID' => 'platform'];
    }
}
