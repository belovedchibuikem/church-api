<?php

namespace Tests\Feature;

use App\Files\FileAssetStatus;
use App\Models\FileAsset;
use App\Models\Person;
use App\Models\SecuritySession;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserFileStreamApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_streams_own_available_file_inline_without_mfa(): void
    {
        Storage::fake('local');
        $user = User::factory()->withPerson()->create();
        $this->authenticate($user);
        $contents = "%PDF-1.4 own document\n";
        $owned = $this->availableAsset($contents, [
            'owner_person_id' => $user->person->getKey(),
            'detected_mime_type' => 'application/pdf',
            'metadata' => ['original_filename' => 'Pastoral Note.pdf'],
        ]);

        $response = $this->get("/api/v1/user/files/{$owned->public_id}");

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('Pastoral Note.pdf', (string) $response->headers->get('content-disposition'));
        $this->assertSame($contents, $response->streamedContent());
    }

    public function test_downloads_own_available_file_as_an_attachment(): void
    {
        Storage::fake('local');
        $user = User::factory()->withPerson()->create();
        $this->authenticate($user);
        $contents = 'attachment body';
        $owned = $this->availableAsset($contents, [
            'owner_person_id' => $user->person->getKey(),
            'detected_mime_type' => 'text/plain',
            'metadata' => ['original_filename' => 'notes.txt'],
        ]);

        $response = $this->get("/api/v1/user/files/{$owned->public_id}?download=1");

        $response
            ->assertOk()
            ->assertDownload('notes.txt');
        $this->assertSame($contents, $response->streamedContent());
    }

    public function test_returns_404_for_another_persons_file_without_leaking_existence(): void
    {
        Storage::fake('local');
        $user = User::factory()->withPerson()->create();
        $this->authenticate($user);
        $other = $this->availableAsset('secret', [
            'owner_person_id' => Person::factory(),
        ]);

        $this->getJson("/api/v1/user/files/{$other->public_id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND')
            ->assertJsonMissingPath('data');
    }

    public function test_returns_404_for_own_unavailable_or_unknown_files(): void
    {
        Storage::fake('local');
        $user = User::factory()->withPerson()->create();
        $this->authenticate($user);
        $quarantined = FileAsset::factory()->create([
            'owner_person_id' => $user->person->getKey(),
            'status' => FileAssetStatus::Quarantined,
        ]);
        Storage::disk('local')->put($quarantined->object_key, 'quarantined');

        foreach ([
            "/api/v1/user/files/{$quarantined->public_id}",
            '/api/v1/user/files/01ARZ3NDEKTSV4RRFFQ69G5FAV',
        ] as $url) {
            $this->getJson($url)
                ->assertNotFound()
                ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND')
                ->assertJsonMissingPath('data');
        }
    }

    public function test_sanitizes_unsafe_original_filenames_in_content_disposition(): void
    {
        Storage::fake('local');
        $user = User::factory()->withPerson()->create();
        $this->authenticate($user);
        $contents = 'safe payload';
        $owned = $this->availableAsset($contents, [
            'owner_person_id' => $user->person->getKey(),
            'metadata' => ['original_filename' => '../../evil<script>.pdf'],
        ]);

        $response = $this->get("/api/v1/user/files/{$owned->public_id}");

        $response->assertOk();
        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString('inline', $disposition);
        $this->assertStringContainsString('evil_script_.pdf', $disposition);
        $this->assertStringNotContainsString('<', $disposition);
        $this->assertStringNotContainsString('..', $disposition);
        $this->assertSame($contents, $response->streamedContent());
    }

    public function test_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/user/files/01ARZ3NDEKTSV4RRFFQ69G5FAV')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'AUTH_UNAUTHENTICATED');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function availableAsset(string $contents, array $attributes = []): FileAsset
    {
        $asset = FileAsset::factory()->available()->create([
            'detected_mime_type' => 'text/plain',
            'byte_size' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            ...$attributes,
        ]);
        Storage::disk('local')->put($asset->object_key, $contents);

        return $asset;
    }

    private function authenticate(User $user, bool $recentMfa = false): SecuritySession
    {
        $securitySession = SecuritySession::factory()->for($user)->create();
        $session = ['security_session_id' => $securitySession->public_id];

        if ($recentMfa) {
            $session['auth.mfa_verified_at'] = now()->utc()->toIso8601String();
        }

        $this->actingAs($user);
        $this->withSession($session);

        return $securitySession;
    }
}
