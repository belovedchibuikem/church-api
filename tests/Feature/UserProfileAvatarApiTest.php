<?php

namespace Tests\Feature;

use App\Files\FileAssetStatus;
use App\Models\FileAsset;
use App\Models\User;
use App\Support\Security\AuthenticateMobileLoginAction;
use App\Support\Security\RegisterDeviceData;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserProfileAvatarApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_member_can_upload_profile_avatar_and_attach_it_to_profile(): void
    {
        Storage::fake('local');
        $user = User::factory()->withPerson()->create(['password' => 'password']);
        $credentials = $this->app->make(AuthenticateMobileLoginAction::class)->handle(
            $user->email,
            'password',
            new RegisterDeviceData(identifier: 'test-installation-id'),
        );
        $credentials->securitySession->forceFill([
            'mfa_verified_at' => now()->utc(),
        ])->save();

        $headers = [
            'Authorization' => 'Bearer '.$credentials->plainAccessToken,
            'X-Device-Identifier' => 'test-installation-id',
            'Idempotency-Key' => 'avatar-upload-test-001',
        ];

        $upload = $this->post('/api/v1/user/files', [
            'purpose' => 'profile.avatar',
            'classification' => 'internal',
            'idempotency_key' => 'avatar-upload-test-001',
            'file' => UploadedFile::fake()->image('avatar.jpg', 200, 200),
        ], $headers);

        $upload->assertCreated();
        $fileId = $upload->json('data.id');
        $this->assertIsString($fileId);

        $asset = FileAsset::query()->where('public_id', $fileId)->first();
        $this->assertInstanceOf(FileAsset::class, $asset);
        $this->assertSame(FileAssetStatus::Available, $asset->status);

        $this->putJson('/api/v1/user/profile', [
            'given_name' => 'Test',
            'family_name' => 'Member',
            'avatar_file_asset_id' => $fileId,
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.profile.avatar_file_id', $fileId);
    }
}
